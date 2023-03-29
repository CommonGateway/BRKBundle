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
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\FileSystemHandleService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BrkService
{

    /**
     * @var array The configuration of the action.
     */
    private array $configuration;

    /**
     * @var array The data from the call.
     */
    private array $data;

    /**
     * @var EntityManagerInterface The Entity Manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface The BRK plugin version of the logger interface.
     */
    private LoggerInterface $brkpluginLogger;

    /**
     * @var FileSystemHandleService The fileSystem Service.
     */
    private FileSystemHandleService $fileSystemService;

    /**
     * @var GatewayResourceService The Gateway Resource Service.
     */
    private GatewayResourceService $resourceService;

    /**
     * @var SynchronizationService The Synchronization Service.
     */
    private SynchronizationService $syncService;

    /**
     * @var MappingService The mapping service.
     */
    private MappingService $mappingService;


    /**
     * @param EntityManagerInterface  $entityManager     The Entity Manager.
     * @param LoggerInterface         $brkpluginLogger   The BRK plugin version of the logger interface.
     * @param FileSystemHandleService $fileSystemService The fileSystem Service.
     * @param GatewayResourceService  $resourceService   The Gateway Resource Service.
     * @param SynchronizationService  $syncService       The Synchronization Service.
     * @param MappingService          $mappingService    The mapping service.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $brkpluginLogger,
        FileSystemHandleService $fileSystemService,
        GatewayResourceService $resourceService,
        SynchronizationService $syncService,
        MappingService $mappingService
    ) {
        $this->entityManager     = $entityManager;
        $this->brkpluginLogger   = $brkpluginLogger;
        $this->fileSystemService = $fileSystemService;
        $this->resourceService   = $resourceService;
        $this->syncService       = $syncService;
        $this->mappingService    = $mappingService;
        $this->configuration     = [];
        $this->data              = [];

    }//end __construct()


    /**
     * Maps Percelen and AppartementsRechten to kadastraalOnroerendeZaak.
     *
     * @param array $objects The object array to map.
     *
     * @return array The resulting objects.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\SyntaxError
     */
    public function mapKadastraalOnroerendeZaken(array $objects): array
    {

        $perceelMapping = $this->resourceService->getMapping("https://brk.commonground.nu/mapping/brkPerceel.mapping.json", 'common-gateway/brk-bundle');
        $arMapping      = $this->resourceService->getMapping("https://brk.commonground.nu/mapping/brkAppartementsrecht.mapping.json", 'common-gateway/brk-bundle');
        $schema         = $this->resourceService->getSchema("https://brk.commonground.nu/schema/kadastraalOnroerendeZaak.schema.json", 'common-gateway/brk-bundle');

        $onroerendeZaken = [];
        foreach ($objects as $object) {
            if (isset($object['Perceel']) === true) {
                $perceel        = $object['Perceel'];
                $onroerendeZaak = $this->mappingService->mapping($perceelMapping, $perceel);
            }

            if (isset($object['Appartementsrecht']) === true) {
                $perceel        = $object['Appartementsrecht'];
                $onroerendeZaak = $this->mappingService->mapping($arMapping, $perceel);
            }

            $onroerendeZaken[] = $this->handleRefObject($schema, $onroerendeZaak);
            $this->entityManager->flush();
        }

        return $onroerendeZaken;

    }//end mapKadastraalOnroerendeZaken()


    /**
     * A BRK handler that is triggered by an action.
     * This function will use $data['query']['filename'] to get a file from the BRK fileSystem.
     * After this file data is mapped in the fileSystemService and returned as a php array,
     * this function (/this service) will convert this data into ObjectEntities (or update existing ones).
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array An array of all the ObjectEntities (->toArray) created/updated
     */
    public function brkHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        // Todo: do we want to use query for this? Here in case we create an Endpoint for this instead of a Command.
        if (isset($this->data['query']['filename']) === false) {
            $this->brkpluginLogger->error("Could not find a filename in the data['query'] array for BrkHandler.");
            return $this->data;
        }

        $endpoint                      = $this->data['query']['filename'];
        $this->configuration['source'] = $this->resourceService->getSource('https://brk.commonground.nu/source/brkFilesystem.source.json', 'common-gateway/brk-bundle');
        $fileDataSet                   = $this->fileSystemService->call($this->configuration['source'], $endpoint);

        $fileDataSet = $this->clearXmlNamespace($fileDataSet);

        $percelen = $this->mapKadastraalOnroerendeZaken($fileDataSet['stand']['KadastraalObjectSnapshot']);

        $data["https://brk.commonground.nu/schema/kadastraalOnroerendeZaak.schema.json"] = $percelen;

        $objects = $this->handleDataSet($data);

        return $objects;

    }//end brkHandler()


    /**
     * Determines if an array is associative.
     *
     * @param array $array The array to check.
     *
     * @return bool Whether the array is associative.
     */
    private function isAssociative(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, (count($array) - 1));

    }//end isAssociative()


    /**
     * Recursively remove namespaces from array keys.
     *
     * @param array $data The array to flatten.
     *
     * @return array The flattened array.
     */
    public function clearXmlNamespace(array $data): array
    {
        $newArray = [];

        foreach ($data as $key => $value) {
            if (is_array($value) === true) {
                $originalValue = $value;
                $value         = $this->clearXmlNamespace($value);

                if ($this->isAssociative($originalValue) === false) {
                    $value = array_values($value);
                }
            }//end if

            $explodedKey       = explode(':', $key);
            $newKey            = end($explodedKey);
            $newArray[$newKey] = $value;
        }//end foreach

        return $newArray;

    }//end clearXmlNamespace()


    /**
     * Handles a php array containing a list of (Schema/Entity) references, each reference containing an array with objects.
     * Each 'object' is an array of fields used to create/update an object with for the corresponding (Schema) reference.
     * This function will create/update an ObjectEntity of the given reference type for each 'object' array.
     *
     * @param array $data The data used to create ObjectEntities with.
     *
     * @return array An array of all the ObjectEntities (->toArray) created/updated.
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
     * Handles an array of objects, each 'object' is an array of fields to create/update an object with for the given Schema.
     * This function will create/update an ObjectEntity of the given Schema for each 'object' in the $refObjects array.
     *
     * @param Entity $schema     A Schema to create ObjectEntities for.
     * @param array  $refObjects The data used to create ObjectEntities with. This array should be an array of objects.
     *
     * @return array An array of all the ObjectEntities (->toArray) created/updated.
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
     * Handles a single object, the given array is an array of fields to create/update an object for the given Schema.
     * This function will create/update a single ObjectEntity of the given Schema.
     *
     * @param Entity $schema    A Schema to create/update an ObjectEntity for.
     * @param array  $refObject The data used to create/update an ObjectEntity with.
     *
     * @return array A single ObjectEntity (->toArray).
     */
    private function handleRefObject(Entity $schema, array $refObject): ObjectEntity
    {
        if (isset($refObject['identificatie']) === false) {
            $this->brkpluginLogger
                ->error(
                    "Could not create a {$schema->getName()} 
                    object because data array does not contain a field 'identificatie'.",
                    ["data" => $refObject]
                );
            return [
                "message" => "Could not create a {$schema->getName()} 
                object because data array does not contain a field 'identificatie'.",
                "data"    => $refObject,
            ];
        }

        $synchronization = $this->syncService->findSyncBySource(
            $this->configuration['source'],
            $schema,
            $refObject['identificatie']
        );

        $synchronization = $this->syncService->synchronize($synchronization, $refObject);

        return $synchronization->getObject();

    }//end handleRefObject()


}//end class
