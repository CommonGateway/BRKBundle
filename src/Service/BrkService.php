<?php
/**
 * The BRK service
 * This service is used to convert xml files from the BRK fileSystem to ObjectEntities.
 *
 * @author  Wilco Louwerse <wilco@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\BRKBundle\src\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\FileSystemHandleService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BrkService
{
    /**
     * @var array
     */
    private array $configuration;
    
    /**
     * @var array
     */
    private array $data;
    
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;
    
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $brkpluginLogger;
    
    /**
     * @var FileSystemHandleService
     */
    private FileSystemHandleService $fileSystemService;
    
    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;
    
    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;
    
    /**
     * @param EntityManagerInterface $entityManager The Entity Manager.
     * @param LoggerInterface $brkpluginLogger The BRK plugin version of the logger interface.
     * @param GatewayResourceService $resourceService The Gateway Resource Service.
     * @param SynchronizationService $syncService The Synchronization Service.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $brkpluginLogger,
        FileSystemHandleService $fileSystemService,
        GatewayResourceService $resourceService,
        SynchronizationService $syncService
    ) {
        $this->entityManager = $entityManager;
        $this->brkpluginLogger = $brkpluginLogger;
        $this->fileSystemService = $fileSystemService;
        $this->resourceService = $resourceService;
        $this->syncService = $syncService;
        $this->configuration = [];
        $this->data = [];
    }//end __construct()
    
    /**
     * A BRK handler that is triggered by an action.
     * This function will use $data['query']['filename'] to get a file from the BRK fileSystem.
     * After this file data is mapped in the fileSystemService and returned as a php array,
     * this function (/this service) will convert this data into ObjectEntities (or update existing ones).
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array A handler must ALWAYS return an array.
     */
    public function BRKHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;
        
        // Todo: do we want to use query for this?
        if (isset($this->data['query']['filename']) === false) {
            $this->brkpluginLogger->error("Could not find a filename in the data['query'] array for BrkHandler.");
            return $this->data;
        }
        $endpoint = $this->data['query']['filename'];
        $this->configuration['source'] = $this->resourceService->getSource('https://brk.commonground.nu/source/brkFilesystem.source.json', 'common-gateway/brk-bundle');
        $fileDataSet = $this->fileSystemService->call($this->configuration['source'], $endpoint);
        
        // Todo: Remove this when mapping is done and works correctly.
        $fileDataSet = [
            "https://brk.commonground.nu/schema/kadastraalOnroerendeZaak.schema.json" => [
                [
                    "identificatie" => "123456789012310",
                    "domein" => ".KadastraalObject",
                    "perceelnummerRotatie" => 123,
                    "toelichtingBewaarder" => "test123"
                ],
                [
                    "identificatie" => "123456789012349",
                    "domein" => ".KadastraalObject",
                    "perceelnummerRotatie" => 321,
                    "toelichtingBewaarder" => "test321"
                ]
            ],
            "https://brk.commonground.nu/schema/hypotheek.schema.json" => [
                [
                    "identificatie" => "123456789012311",
                    "domein" => ".KadastraalObject",
                    "bedragZekerheidsstelling" => [
                        "som" => 1001,
                        "valuta" => [
                            "code" => "311",
                            "waarde" => "ABC00"
                        ]
                    ]
                ]
            ]
        ];
        
        // Todo: what if we only have 1 object and not a list of references with each a list of objects? Or just a list of objects for 1 reference?
        // Todo: ^in this case, we should use handleRefObjects() or handleRefObject() instead of handleDataSet()
        $objects = $this->handleDataSet($fileDataSet);
        
        $this->entityManager->flush();
        
        return $objects;
    }//end BrkHandler()
    
    /**
     * Handles a php array containing a list of (Schema/Entity) references, each reference containing an array with objects.
     * Each 'object' is an array of fields used to create/update an object with for the corresponding (Schema/Entity) reference.
     * This function will create/update an ObjectEntity of the given reference type for each 'object' array.
     *
     * @param array $data The data used to create ObjectEntities with. This array should contain references with each an array of objects.
     *
     * @return array An array of all the ObjectEntities (->toArray) created.
     */
    private function handleDataSet(array $data): array
    {
        $objects = [];
        
        foreach ($data as $reference => $refObjects) {
            $schema = $this->resourceService->getSchema($reference, 'common-gateway/brk-bundle');
            if ($schema === null) {
                continue;
            }
            
            $objects[$reference] = $this->handleRefObjects($schema, $refObjects);
        }
        
        return $objects;
    }//end handleDataSet()
    
    /**
     * Handles an array of objects, each 'object' is an array of fields used to create/update an object with for the given Schema.
     * This function will create/update an ObjectEntity of the given Schema for each 'object' in the $refObjects array.
     *
     * @param Entity $schema A Schema to create ObjectEntities for.
     * @param array $refObjects The data used to create ObjectEntities with. This array should be an array of objects.
     *
     * @return array An array of all the ObjectEntities (->toArray) created.
     */
    private function handleRefObjects(Entity $schema, array $refObjects): array
    {
        $objects = [];
        
        foreach ($refObjects as $refObject) {
            $objects[] = $this->handleRefObject($schema, $refObject);
        }
        
        return $objects;
    }//end handleRefObjects()
    
    /**
     * Handles a single object, the given $refObject array is an array of fields used to create/update an object with for the given Schema.
     * This function will create/update a single ObjectEntity of the given Schema.
     *
     * @param Entity $schema A Schema to create/update an ObjectEntity for.
     * @param array $refObject The data used to create/update an ObjectEntity with. This array should contain the fields for this ObjectEntity.
     *
     * @return array A single ObjectEntity (->toArray).
     */
    private function handleRefObject(Entity $schema, array $refObject): array
    {
        if (isset($refObject['identificatie']) === false) {
            $this->brkpluginLogger->error("Could not create a {$schema->getName()} object because data array does not contain a field 'identificatie'.", ["data" => $refObject]);
            return ["Could not create a {$schema->getName()} object because data array does not contain a field 'identificatie'.", "data" => $refObject];
        }
        $synchronization = $this->syncService->findSyncBySource($this->configuration['source'], $schema, $refObject['identificatie']);
        
        $synchronization = $this->syncService->synchronize($synchronization, $refObject);
        
        return $synchronization->getObject()->toArray();
    }//end handleRefObject()
}
