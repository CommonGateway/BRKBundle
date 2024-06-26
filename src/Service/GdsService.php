<?php
/**
 * A wrapper for the GDS service.
 *
 * This service connects to the GDS download service of the kadaster in order to retrieve new data.
 *
 * @package common-gateway/brk-bundle
 *
 * @author  Robert Zondervan <robert@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\BRKBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\FileSystemHandleService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;


class GdsService
{

    /**
     * The call service.
     *
     * @var CallService
     */
    private CallService $callService;

    /**
     * The gateway resource service.
     *
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * The file system handle service.
     *
     * @var FileSystemHandleService
     */
    private FileSystemHandleService $fshService;

    /**
     * The BRK service.
     *
     * @var BrkService
     */
    private BrkService $brkService;

    /**
     * The cache service.
     *
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * The entity manager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * The event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;


    /**
     * The service constructor.
     *
     * @param CallService              $callService     The call service.
     * @param GatewayResourceService   $resourceService The resource service.
     * @param FileSystemHandleService  $fshService      The file system handle service.
     * @param BrkService               $brkService      The BRK service.
     * @param CacheService             $cacheService    The cache service.
     * @param EntityManagerInterface   $entityManager   The entity manager.
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher.
     */
    public function __construct(
        CallService $callService,
        GatewayResourceService $resourceService,
        FileSystemHandleService $fshService,
        BrkService $brkService,
        CacheService $cacheService,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->resourceService = $resourceService;
        $this->fshService      = $fshService;
        $this->callService     = $callService;
        $this->brkService      = $brkService;
        $this->cacheService    = $cacheService;
        $this->entityManager   = $entityManager;
        $this->eventDispatcher = $eventDispatcher;

    }//end __construct()


    /**
     * Creates the content for a soap message to the GDS download service.
     *
     * @param DateTime $lastSynced    The date and time the data has last been synced.
     * @param array    $configuration Whether or not the call is a test call.
     *
     * @return array The resulting request message.
     */
    private function createRequestMessage(DateTime $lastSynced, array $configuration): array
    {
        $now            = new DateTime();
        $notYetReported = 'true';

        if ($configuration['test'] === true) {
            $notYetReported = 'false';
        }

        return [
            "@xmlns:SOAP-ENV" => "http://schemas.xmlsoap.org/soap/envelope/",
            "@xmlns:v20"      => "http://www.kadaster.nl/schemas/gds2/service/afgifte-bestandenlijstopvragen/v20170401",
            "@xmlns:v201"     => "http://www.kadaster.nl/schemas/gds2/afgifte-bestandenlijstselectie/v20170401",
            "@xmlns:v202"     => "http://www.kadaster.nl/schemas/gds2/afgifte-proces/v20170401",
            "SOAP-ENV:Body"   => [
                "v20:BestandenlijstOpvragenRequest" => [
                    "v20:verzoek" => [
                        "v201:AfgifteSelectieCriteria" => [
                            'v201:BestandKenmerken'     => ["v201:contractnummer" => "{$configuration['contractNumber']}"],
                            "v202:Periode"              => [
                                "v202:DatumTijdVanaf"  => $lastSynced->format('Y-m-d\TH:i:s.v\Z'),
                                "v202:DatumTijdTotmet" => $now->format('Y-m-d\TH:i:s.v\Z'),
                            ],
                            "v202:NogNietGerapporteerd" => $notYetReported,
                            "v202:Sortering"            => [
                                "v202:Kolom"    => "DATUM_AANMELDING",
                                "v202:Volgorde" => "DESC",
                            ],
                        ],
                    ],
                ],
            ],
        ];

    }//end createRequestMessage()


    /**
     * Get urls for data from the response from GDS2.
     *
     * @param array $result The GDS2 response.
     *
     * @return array
     */
    private function getDataUrls(array $result): array
    {
        $locations = [];
        $baseUrls  = $result['SOAP-ENV:Body']['ns9:BestandenlijstOpvragenResponse']['ns9:antwoord']['ns3:BaseURLSet']['ns4:BaseURL'];

        foreach ($baseUrls as $baseUrl) {
            if ($baseUrl['@type'] === 'certificaat') {
                break;
            }
        }

        $files = $result['SOAP-ENV:Body']['ns9:BestandenlijstOpvragenResponse']['ns9:antwoord']['ns5:BestandenLijst']['ns5:Afgifte'];

        if ($this->brkService->isAssociative($files) === true) {
            $fileUrl     = $files['ns8:digikoppeling-external-datareferences']['ns8:data-reference']['ns8:transport']['ns8:location']['ns8:senderUrl']['#'];
            $mime        = $files['ns8:digikoppeling-external-datareferences']['ns8:data-reference']['ns8:content']['@contentType'];
            $locations[] = [
                'url'  => "{$baseUrl['#']}/$fileUrl",
                'mime' => "$mime",
            ];

            return $locations;
        }

        foreach ($files as $file) {
            $fileUrl     = $file['ns8:digikoppeling-external-datareferences']['ns8:data-reference']['ns8:transport']['ns8:location']['ns8:senderUrl']['#'];
            $mime        = $file['ns8:digikoppeling-external-datareferences']['ns8:data-reference']['ns8:content']['@contentType'];
            $locations[] = [
                'url'  => "{$baseUrl['#']}/$fileUrl",
                'mime' => "$mime",
            ];
        }

        return $locations;

    }//end getDataUrls()


    private function getIdentifiers(array $data): array
    {
        $ids = array_map(
            callback: function ($item) {
                if (is_array($item) === true && $this->brkService->isAssociative(array: $item) === true && isset($item['identificatie']['#']) === true) {
                    return $item['identificatie']['#'];
                } else if (is_array(value: $item) === true) {
                    $ids = [];
                    foreach ($item as $subItem) {
                        if (is_array($subItem) === true && $this->brkService->isAssociative($subItem) === true && isset($subItem['identificatie']['#']) === true) {
                            $ids[] = isset($subItem['identificatie']['#']);
                        }
                    }

                    return $ids;
                }

                return null;
            },
            arrays: $data
        );

        array_walk_recursive(
            array: $ids,
            callback: function ($value) {
                global $array;
                $array[] = $value;
            }
        );

        return $ids;

    }//end getIdentifiers()


    public function removeDeletedObjects(array $was, array $becomes)
    {
        $wasIds     = $this->getIdentifiers(data: $was);
        $becomesIds = $this->getIdentifiers(data: $becomes);

        foreach ($wasIds as $wasId) {
            if (in_array(needle: $wasId, haystack: $becomesIds) === false) {
                $objects = $this->cacheService->searchObject(filter: ['identificatie' => $wasId])['results'];
                if (count(value: $objects) > 0) {
                    $object = new ObjectEntity($objects[0]['_self']['schema']['ref']);
                    $object->setId(Uuid::fromString($objects[0]['_id']));
                    $this->cacheService->removeObject(object: $object, softDelete: true);
                }
            }
        }

    }//end removeDeletedObjects()


    /**
     * Creates snapshots in the database and dispatch an event to process them.
     *
     * @param array $file The contents of a file.
     *
     * @return array The created snapshots.
     */
    public function createSnapShots(array $file): array
    {
        $snapshotSchema = $this->resourceService
            ->getSchema('https://brk.commonground.nu/schema/snapshot.schema.json', 'common-gateway/brk-bundle');

        $data = $this->brkService->clearXmlNamespace($file);

        $snapshotsToMap   = [];
        $createdSnapshots = [];

        $this->removeDeletedObjects(was: $data['wordt']['KadastraalObjectSnapshot'], becomes: $data['was']['KadastraalObjectSnapshot']);

        $snapshotsToMap[] = $data['wordt']['KadastraalObjectSnapshot'];

        if ($this->brkService->isAssociative($data['wordt']['KadastraalObjectSnapshot']) === false) {
            $snapshotsToMap = [];
            foreach ($data['wordt']['KadastraalObjectSnapshot'] as $snapshot) {
                $snapshotsToMap[] = $snapshot;
            }
        }

        foreach ($snapshotsToMap as $snapshot) {
            $snapshots = $this->cacheService->searchObjects(
                null,
                ['referentie' => $snapshot['referentie']],
                [$snapshotSchema->getId()->toString()]
            )['results'];

            if (count($snapshots) !== 0) {
                continue;
            }

            $object = new ObjectEntity($snapshotSchema);
            $object->hydrate(
                [
                    'snapshot'   => $snapshot,
                    'referentie' => $snapshot['referentie'],
                ]
            );
            $this->entityManager->persist($object);

            $event = new ActionEvent(
                'commongateway.action.event',
                ['snapshotId' => $object->getId()->toString()],
                'brk.snapshot.stored'
            );
            $this->eventDispatcher->dispatch($event, 'commongateway.action.event');

            $createdSnapshots[] = $object->getId()->toString();
        }//end foreach

        return $createdSnapshots;

    }//end createSnapShots()


    /**
     * Loops through the files in the zip files and create snapshots for them.
     *
     * @param array $files The files in a zip file.
     *
     * @return array The created snapshot objects.
     */
    public function getSnapshotsForFiles(array $files): array
    {
        $snapshots = [];
        foreach ($files as $file) {
            $snapshots = array_merge($this->createSnapShots($file), $snapshots);
        }

        return $snapshots;

    }//end getSnapshotsForFiles()


    /**
     * Downloads and decodes the zip files and sends the data to snapshot objects.
     *
     * @param array $locations     The locations to retrieve the zip files from.
     * @param array $configuration The configuration of the service.
     *
     * @return array The snapshot objects created.
     */
    public function fetchData(array $locations, array $configuration): array
    {
        $source = $this->resourceService->getSource($configuration['downloadSource'], 'common-gateway/brk-bundle');
        $data   = [];

        foreach ($locations as $location) {
            if ($location['mime'] !== 'application/zip') {
                continue;
            }

            if (str_contains($location['url'], $source->getLocation()) === true) {
                $endpoint = substr($location['url'], strlen($source->getLocation()));
                $file     = $this->callService->call($source, $endpoint, 'GET')->getBody()->getContents();
                $fileData = $this->fshService->decodeFile($file, '', 'zip');
                $data     = array_merge($this->getSnapshotsForFiles($fileData), $data);
            }
        }

        return $data;

    }//end fetchData()


    /**
     * Calls the GDS source with configured data
     *
     * @param array $data          The data for the action.
     * @param array $configuration The configuration of the action.
     *
     * @return array The data downloaded from the GDS service.
     */
    public function gdsDataHandler(array $data, array $configuration): array
    {
        $source     = $this->resourceService->getSource($configuration['gdsSource'], 'common-gateway/brk-bundle');
        $lastSynced = new DateTime($configuration['lastSynchronization']);

        $location = $configuration['endpoint'];
        $message  = $this->createRequestMessage($lastSynced, $configuration);

        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'SOAP-ENV:Envelope']);
        $body       = $xmlEncoder->encode($message, 'xml');
        $response   = $this->callService->call(
            $source,
            $location,
            'POST',
            [
                'body'    => $body,
                'headers' => [
                    'SOAPAction'   => "",
                    'Content-Type' => 'text/xml;charset="utf-8"',
                ],
            ]
        );
        $result     = $this->callService->decodeResponse($source, $response);

        $locations = $this->getDataUrls($result);
        $data      = array_merge_recursive($data, $this->fetchData($locations, $configuration));

        return $data;

    }//end gdsDataHandler()


}//end class
