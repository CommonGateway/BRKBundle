<?php
/**
 * The BRK service
 * This service is used to convert xml files from the BRK fileSystem to ObjectEntities.
 *
 * @author  Wilco Louwerse <wilco@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\BRKBundle\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\FileSystemHandleService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BRKService
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
     * @param EntityManagerInterface $entityManager The Entity Manager.
     * @param LoggerInterface $brkpluginLogger The BRK plugin version of the logger interface.
     * @param GatewayResourceService $resourceService The Gateway Resource Service.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $brkpluginLogger,
        FileSystemHandleService $fileSystemService,
        GatewayResourceService $resourceService
    ) {
        $this->entityManager = $entityManager;
        $this->brkpluginLogger = $brkpluginLogger;
        $this->fileSystemService = $fileSystemService;
        $this->resourceService = $resourceService;
        $this->configuration = [];
        $this->data = [];
    }//end __construct()
    
    /**
     * An BRK handler that is triggered by an action.
     *
     * @return array A handler must ALWAYS return an array.
     */
    public function BRKHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;
        
        // Todo: do we want to use query for this?
        if (isset($this->data['query']['filename']) === false) {
            // Todo: error / user feedback
            return $this->data;
        }
        $endpoint = $this->data['query']['filename'];
        $source = $this->resourceService->getSource('https://brk.commonground.nu/source/brkFilesystem.source.json', 'common-gateway/brk-bundle');
        $fileDataSet = $this->fileSystemService->call($source, $endpoint);
        
        // Todo: temporary:
        $fileDataSet = [
            "https://brk.commonground.nu/schema/kadastraalOnroerendeZaak.schema.json" => [
                [
                    "identificatie" => "123456789012310",
                    "domein" => ".KadastraalObject",
                    "perceelnummerRotatie" => 123,
                    "toelichtingBewaarder" => "tes123"
                ],
                [
                    "identificatie" => "123456789012349",
                    "domein" => ".KadastraalObject",
                    "perceelnummerRotatie" => 321,
                    "toelichtingBewaarder" => "tes321"
                ]
            ],
            "https://brk.commonground.nu/schema/hypotheek.schema.json" => [
                [
                    "identificatie" => "123456789012311",
                    "domein" => ".KadastraalObject",
                    "description" => "Bij een hypotheek op de kadastraal onroerende zaak dient het eigendomsrecht van de hypotheekgever als onderpand voor een geldlening of krediet bij de hypotheekhouder (geldverstrekker).",
                    "bedragZekerheidsstelling" => [
                        "description" => "1001 eu",
                        "som" => 1001,
                        "valuta" => [
                            "code" => "311",
                            "waarde" => "ABC00"
                        ]
                    ]
                ]
            ]
        ];
        
        // Todo: what if we only have 1 object and not a list of references with each a list of objects? Or a list of objects for 1 reference?
        // Todo: ^in this case, use handleRefObjects() or handleRefObject() instead
        return $this->handleDataSet($fileDataSet);
    }//end BRKHandler()
    
    /**
     * @todo
     *
     * @param array $data
     *
     * @return array
     */
    private function handleDataSet(array $data): array
    {
        $objects = [];
        
        foreach ($data as $reference => $refObjects) {
            $entity = $this->resourceService->getSchema($reference, 'common-gateway/brk-bundle');
            if ($entity === null) {
                continue;
            }
            
            $objects[$reference] = $this->handleRefObjects($entity, $refObjects);
        }
        
        return $objects;
    }//end handleDataSet()
    
    /**
     * @todo
     *
     * @param Entity $entity
     * @param array $refObjects
     *
     * @return array
     */
    private function handleRefObjects(Entity $entity, array $refObjects): array
    {
        $objects = [];
        
        foreach ($refObjects as $refObject) {
            $objects[] = $this->handleRefObject($entity, $refObject);
        }
        
        return $objects;
    }//end handleRefObjects()
    
    /**
     * @todo
     *
     * @param Entity $entity
     * @param array $refObject
     *
     * @return array
     */
    private function handleRefObject(Entity $entity, array $refObject): array
    {
        // Todo: check if $refObject has an id, if so check if an object with this already exists (through Synchronization sourceId)
        // Todo: what if $refObject has no id?
        
        $object = new ObjectEntity($entity);
        $object->hydrate($refObject);
        $this->entityManager->persist($object);
        
        // Todo: also create a Synchronization with hash and sourceId
        
        return $object->toArray();
    }//end handleRefObject()
}
