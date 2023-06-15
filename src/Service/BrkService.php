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
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Event\ActionEvent;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\FileSystemHandleService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

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
     * @var EventDispatcherInterface The event dispatcher.
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var CacheService The cache service.
     */
    private CacheService $cacheService;


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
        MappingService $mappingService,
        CacheService $cacheService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher   = $eventDispatcher;
        $this->entityManager     = $entityManager;
        $this->brkpluginLogger   = $brkpluginLogger;
        $this->fileSystemService = $fileSystemService;
        $this->resourceService   = $resourceService;
        $this->syncService       = $syncService;
        $this->mappingService    = $mappingService;
        $this->cacheService      = $cacheService;
        $this->configuration     = [];
        $this->data              = [];

    }//end __construct()


    /**
     * Map a single instance of one mapping.
     *
     * @param Mapping $mapping The mapping to use for the mapping.
     * @param array   $object  The object to map.
     *
     * @return array The mapped object.
     */
    public function mapSingle(Mapping $mapping, array $object): array
    {
        return $this->mappingService->mapping($mapping, $object);

    }//end mapSingle()


    /**
     * Map multiple instances of one mapping.
     *
     * @param Mapping $mapping The mapping to use for the mapping
     * @param array   $objects The objects to map.
     *
     * @return array The mapped objects.
     */
    public function mapMultiple(Mapping $mapping, array $objects): array
    {
        $results = [];

        foreach ($objects as $object) {
            $results[] = $this->mapSingle($mapping, $object);
        }

        return $results;

    }//end mapMultiple()


    /**
     * Find the children in an array that have the parent in the property 'parent'.
     *
     * @param array  $children The child objects.
     * @param string $parent   The identifier of the parents to find children for.
     *
     * @return array The children for the given parent.
     */
    public function getChildren(array $children, string $parent): array
    {
        $childrenOfParent = [];
        foreach ($children as $child) {
            if ($child['parent'] === $parent) {
                $childrenOfParent[] = $child;
            }
        }

        return $childrenOfParent;

    }//end getChildren()


    /**
     * Connects parents and children that have an inversed relationship in the source.
     *
     * @param array  $parents  The parents to connect the children to.
     * @param array  $children The children to connect to the parents.
     * @param string $property The property to connect the children into.
     * @param bool   $singular If the property is singular or plural, resulting in 1 or n results being connected.
     *
     * @return array The array of parents with children connected.
     */
    public function connectInversed(array $parents, array $children, string $property, bool $singular=true): array
    {
        foreach ($parents as $key => $parent) {
            if (isset($parent['identificatie']) === true) {
                $childrenOfParent = $this->getChildren($children, $parent['identificatie']);
                if ($singular === true) {
                    $last = end($childrenOfParent);
                    $index = 0;
                    foreach ($childrenOfParent as $child) {
                        $parent[$property] = $child;
                        $parent['identificatie'] = $parent['identificatie'].$index;
                        if($child !== $last) {
                            $parents[] = $parent;
                        } else {
                            $parents[$key] = $parent;
                        }
                        $index++;
                    }
                    continue;
                } else {
                    $parents[$key][$property] = $childrenOfParent;
                }
            }
        }
        return $parents;

    }//end connectInversed()


    /**
     * Connects Zakelijk Gerechtigden objects to onroerende zaak objects.
     *
     * @param ObjectEntity $object          The Zakelijk Gerechtigde object.
     * @param array        $onroerendeZaken The oroerende zaak objects that the Zakelijk Gerechtigde should connect to.
     *
     * @return ObjectEntity The updated zakelijk gerechtigde object.
     */
    private function addZGtoOZs(ObjectEntity $object, array $onroerendeZaken): ObjectEntity
    {
        foreach ($onroerendeZaken as $onroerendeZaak) {
            $ozObject = $this->entityManager->getRepository('App:ObjectEntity')->find($onroerendeZaak['_id']);
            if ($ozObject !== null) {
                $value = array_merge([$object], $ozObject->getValueObject('zakelijkGerechtigdeIdentificaties')->getObjects()->toArray());
                $ozObject->hydrate(['zakelijkGerechtigdeIdentificaties' => $value]);
                $this->entityManager->persist($ozObject);
            }
        }

        $this->entityManager->flush();

        return $object;

    }//end addZGtoOZs()


    /**
     * Maps zakelijk gerechtigden within an snapshot.
     *
     * @param array $snapshot The snapshot to map zakelijk gerechtigden for.
     *
     * @return array The mapped zakelijk gerechtigden.
     */
    public function mapZakelijkGerechtigden(array $snapshot): array
    {

        $zgMapping             = $this->resourceService->getMapping(
            'https://brk.commonground.nu/mapping/brkZakelijkRechtToZakelijkGerechtigde.mapping.json',
            'common-gateway/brk-bundle'
        );
        $tenaamstellingMapping = $this->resourceService->getMapping(
            'https://brk.commonground.nu/mapping/brkTenaamstelling.mapping.json',
            'common-gateway/brk-bundle'
        );
        $aantekeningMapping    = $this->resourceService->getMapping(
            'https://brk.commonground.nu/mapping/brkAantekening.mapping.json',
            'common-gateway/brk-bundle'
        );
        $zgSchema              = $this->resourceService->getSchema(
            'https://brk.commonground.nu/schema/zakelijkGerechtigde.schema.json',
            'common-gateway/brk-bundle'
        );

        $tenaamstellingen     = [];
        $zakelijkGerechtigden = [];
        $aantekeningen        = [];

        if (isset($snapshot['ZakelijkRecht']) === true && $this->isAssociative($snapshot['ZakelijkRecht']) === false) {
            $zakelijkGerechtigden = $this->mapMultiple($zgMapping, $snapshot['ZakelijkRecht']);
        } else if (isset($snapshot['ZakelijkRecht']) === true) {
            $zakelijkGerechtigden = [$this->mapSingle($zgMapping, $snapshot['ZakelijkRecht'])];
        }

        if (isset($snapshot['Tenaamstelling']) === true && $this->isAssociative($snapshot['Tenaamstelling']) === false) {
            $tenaamstellingen = $this->mapMultiple($tenaamstellingMapping, $snapshot['Tenaamstelling']);
        } else if (isset($snapshot['Tenaamstelling']) === true) {
            $tenaamstellingen = [$this->mapSingle($tenaamstellingMapping, $snapshot['Tenaamstelling'])];
        }

        if (isset($snapshot['Aantekening']) === true && $this->isAssociative($snapshot['Aantekening']) === false) {
            $aantekeningen = $this->mapMultiple($aantekeningMapping, $snapshot['Aantekening']);
        } else if (isset($snapshot['Aantekening']) === true) {
            $aantekeningen = [$this->mapSingle($aantekeningMapping, $snapshot['Tenaamstelling'])];
        }

        $tenaamstellingen     = $this
            ->connectInversed($tenaamstellingen, $aantekeningen, 'aantekeningen', false);
        $zakelijkGerechtigden = $this
            ->connectInversed($zakelijkGerechtigden, $tenaamstellingen, 'tenaamstelling');

        foreach ($zakelijkGerechtigden as $key => $value) {
            if (isset($value['tenaamstelling']['tenNameVan']) === true) {
                $value['persoon'] = $value['tenaamstelling']['tenNameVan'];
                unset($value['tenaamstelling']['tenNameVan']);
                $zakelijkGerechtigden[$key] = $value;
            }
        }

        $objects = [];

        foreach ($zakelijkGerechtigden as $zakelijkGerechtigde) {
            if($zakelijkGerechtigde['parent'] !== '') {
                $previousParent = $zakelijkGerechtigde['parent'];
            } else {
                $zakelijkGerechtigde['parent'] = $previousParent;
            }

            $object          = $this->handleRefObject($zgSchema, $zakelijkGerechtigde);
            $onroerendeZaken = $this->cacheService->searchObjects(
                '',
                [
                    'identificatie'    => $zakelijkGerechtigde['parent'],
                    '_self.schema.ref' => 'https://brk.commonground.nu/schema/kadastraalOnroerendeZaak.schema.json',
                ]
            )['results'];

            $this->addZGtoOZs($object, $onroerendeZaken);
            $objects[] = $object;
        }

        return $objects;

    }//end mapZakelijkGerechtigden()


    /**
     * Maps onroerende zaken within a snapshot.
     *
     * @param array $snapshot The snapshot to map onroerende zaken for.
     *
     * @return array The resulting onroerende zaken.
     */
    public function mapOnroerendeZaken(array $snapshot): array
    {
        $perceelMapping = $this->resourceService->getMapping(
            "https://brk.commonground.nu/mapping/brkPerceel.mapping.json",
            'common-gateway/brk-bundle'
        );
        $arMapping      = $this->resourceService->getMapping(
            "https://brk.commonground.nu/mapping/brkAppartementsrecht.mapping.json",
            'common-gateway/brk-bundle'
        );
        $ozSchema       = $this->resourceService->getSchema(
            "https://brk.commonground.nu/schema/kadastraalOnroerendeZaak.schema.json",
            'common-gateway/brk-bundle'
        );

        $onroerendeZaken = [];

        if (isset($snapshot['Perceel']) === true && $this->isAssociative($snapshot['Perceel']) === false) {
            $onroerendeZaken = array_merge($this->mapMultiple($perceelMapping, $snapshot['Perceel']), $onroerendeZaken);
        } else if (isset($snapshot['Perceel']) === true) {
            $onroerendeZaken[] = $this->mapSingle($perceelMapping, $snapshot['Perceel']);
        }

        if (isset($snapshot['Appartementsrecht']) === true
            && $this->isAssociative($snapshot['Appartementsrecht']) === false
        ) {
            $onroerendeZaken = array_merge($this->mapMultiple($arMapping, $snapshot['Appartementsrecht']), $onroerendeZaken);
        } else if (isset($snapshot['Appartementsrecht']) === true) {
            $onroerendeZaken[] = $this->mapSingle($arMapping, $snapshot['Appartementsrecht']);
        }

        $objects = [];

        foreach ($onroerendeZaken as $onroerendeZaak) {
            $objects[] = $this->handleRefObject($ozSchema, $onroerendeZaak);
        }

        return $objects;

    }//end mapOnroerendeZaken()


    /**
     * Maps personen from BRK snapshot.
     *
     * @param array $snapshot The snapshot to map personen for.
     *
     * @return array The resulting personen.
     */
    public function mapPersonen(array $snapshot): array
    {
        $nnpMapping = $this->resourceService
            ->getMapping("https://brk.commonground.nu/mapping/brkNnp.mapping.json", 'common-gateway/brk-bundle');
        $npMapping  = $this->resourceService
            ->getMapping("https://brk.commonground.nu/mapping/brkNp.mapping.json", 'common-gateway/brk-bundle');
        $npSchema   = $this->resourceService->getSchema(
            "https://brk.commonground.nu/schema/kadasterNatuurlijkPersoon.schema.json",
            'common-gateway/brk-bundle'
        );
        $nnpSchema  = $this->resourceService->getSchema(
            "https://brk.commonground.nu/schema/kadasterNietNatuurlijkPersoon.schema.json",
            'common-gateway/brk-bundle'
        );

        $objects = [];
        if (isset($snapshot['NietNatuurlijkPersoon']) === true
            && $this->isAssociative($snapshot['NietNatuurlijkPersoon']) === true
        ) {
            $objects[] = $this
                ->handleRefObject($nnpSchema, $this->mapSingle($nnpMapping, $snapshot['NietNatuurlijkPersoon']));
        } else if (isset($snapshot['NietNatuurlijkPersoon']) === true) {
            $objects = array_merge(
                $this->handleRefObjects($nnpSchema, $this->mapMultiple($nnpMapping, $snapshot['NietNatuurlijkPersoon'])),
                $objects
            );
        }

        if (isset($snapshot['NatuurlijkPersoon']) === true
            && $this->isAssociative($snapshot['NatuurlijkPersoon']) === true
        ) {
            $objects[] = $this->handleRefObject($npSchema, $this->mapSingle($npMapping, $snapshot['NatuurlijkPersoon']));
        } else if (isset($snapshot['NatuurlijkPersoon']) === true) {
            $objects = array_merge(
                $this->handleRefObjects($npSchema, $this->mapMultiple($npMapping, $snapshot['NatuurlijkPersoon'])),
                $objects
            );
        }

        return $objects;

    }//end mapPersonen()


    /**
     * Maps publiekrechtelijke beperkingen for a snapshot.
     *
     * @param array $snapshot The snapshot to map publiekrechtelijke beperkingen for.
     *
     * @return array
     */
    public function mapPubliekrechtelijkeBeperkingen(array $snapshot): array
    {
        $publiekBeperkingMapping = $this->resourceService->getMapping(
            "https://brk.commonground.nu/mapping/brkPubliekrechtelijkeBeperking.mapping.json",
            'common-gateway/brk-bundle'
        );
        $publiekBeperkingSchema  = $this->resourceService->getSchema(
            "https://brk.commonground.nu/schema/publiekrechtelijkeBeperking.schema.json",
            'common-gateway/brk-bundle'
        );

        $objects = [];
        if (isset($snapshot['PubliekrechtelijkeBeperking']) === true
            && $this->isAssociative($snapshot['PubliekrechtelijkeBeperking']) === true
        ) {
            $objects[] = $this->handleRefObject(
                $publiekBeperkingSchema,
                $this->mapSingle($publiekBeperkingMapping, $snapshot['PubliekrechtelijkeBeperking'])
            );
        } else if (isset($snapshot['PubliekrechtelijkeBeperking']) === true) {
            $objects = array_merge(
                $this->handleRefObjects(
                    $publiekBeperkingSchema,
                    $this->mapMultiple($publiekBeperkingMapping, $snapshot['PubliekrechtelijkeBeperking'])
                ),
                $objects
            );
        }

        return $objects;

    }//end mapPubliekrechtelijkeBeperkingen()


    /**
     * Maps BRK object arrays to the desired resources.
     *
     * @param array $object The objects to map.
     * @param int   $start  The start of the array to map.
     * @param int   $length The maximum number of elements to map.
     *
     * @return array
     * @throws LoaderError
     * @throws SyntaxError
     */
    public function mapBrkObject(array $object, int $start=0, int $length=10000): array
    {
        $onroerendeZaken      = [];
        $personen             = [];
        $publiekeBeperkingen  = [];
        $zakelijkGerechtigden = [];

        $onroerendeZaken = array_merge($this->mapOnroerendeZaken($object), $onroerendeZaken);
        $this->entityManager->flush();
        $this->entityManager->flush();

        $personen             = array_merge($this->mapPersonen($object), $personen);
        $zakelijkGerechtigden = array_merge($this->mapZakelijkGerechtigden($object), $zakelijkGerechtigden, $onroerendeZaken);
        $publiekeBeperkingen  = array_merge($this->mapPubliekrechtelijkeBeperkingen($object), $publiekeBeperkingen);

        $this->entityManager->flush();
        $this->entityManager->flush();

        return array_merge($onroerendeZaken, $publiekeBeperkingen, $personen, $zakelijkGerechtigden);

    }//end mapBrkObject()


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
        $this->configuration['source'] = $this->resourceService
            ->getSource('https://brk.commonground.nu/source/brkFilesystem.source.json', 'common-gateway/brk-bundle');
        $this->configuration['schema'] = $this->resourceService
            ->getSchema('https://brk.commonground.nu/schema/snapshot.schema.json', 'common-gateway/brk-bundle');
        $fileDataSet                   = $this->fileSystemService->call($this->configuration['source'], $endpoint);

        $fileDataSet = $this->clearXmlNamespace($fileDataSet);

        foreach ($fileDataSet['stand']['KadastraalObjectSnapshot'] as $object) {
            $snapshots = $this->cacheService->searchObjects(
                null,
                ['referentie' => $object['referentie']],
                [$this->configuration['schema']->getId()->toString()]
            )['results'];

            if (count($snapshots) !== 0) {
                continue;
            }

            $snapshot = new ObjectEntity($this->configuration['schema']);
            $snapshot->hydrate(
                [
                    'snapshot'   => $object,
                    'referentie' => $object['referentie'],
                ]
            );
            $this->entityManager->persist($snapshot);
            $this->entityManager->flush();

            $event = new ActionEvent(
                'commongateway.action.event',
                ['snapshotId' => $snapshot->getId()->toString()],
                'brk.snapshot.stored'
            );
            $this->eventDispatcher->dispatch($event, 'commongateway.action.event');
        }//end foreach

        // $objects = $this->mapBrkObjects($fileDataSet['stand']['KadastraalObjectSnapshot']);
        return $data;

    }//end brkHandler()


    /**
     * Maps a single snapshot object to BRK objects.
     *
     * @param array $data          The action data.
     * @param array $configuration The action configuration.
     *
     * @return array
     */
    public function snapshotHandler(array $data, array $configuration): array
    {
        $snapshotId = $data['snapshotId'];
        $snapshot   = $this->entityManager->getRepository('App:ObjectEntity')->find($snapshotId);

        $this->configuration['source'] = $this->resourceService
            ->getSource('https://brk.commonground.nu/source/brkFilesystem.source.json', 'common-gateway/brk-bundle');

        $this->mapBrkObject($snapshot->getValue('snapshot'));
        $snapshot->hydrate(['processedDateTime' => 'now']);
        return $data;

    }//end snapshotHandler()


    /**
     * Determines if an array is associative.
     *
     * @param array $array The array to check.
     *
     * @return bool Whether the array is associative.
     */
    public function isAssociative(array $array): bool
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
