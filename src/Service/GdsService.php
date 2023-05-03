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
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\FileSystemHandleService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use DateTime;
use Symfony\Component\Serializer\Encoder\XmlEncoder;


class GdsService
{

    private CallService $callService;

    private GatewayResourceService $grService;

    private FileSystemHandleService $fshService;


    public function __construct(
        CallService $callService,
        GatewayResourceService $grService,
        FileSystemHandleService $fshService
    ) {
        $this->grService   = $grService;
        $this->fshService  = $fshService;
        $this->callService = $callService;

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
        $now            = new \DateTime();
        $notYetReported = 'true';

        if ($configuration['test'] === true) {
            $notYetReported = 'false';
        }

        return [
            "@xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
            "@xmlns:v20"     => "http://www.kadaster.nl/schemas/gds2/service/afgifte-bestandenlijstopvragen/v20170401",
            "@xmlns:v201"    => "http://www.kadaster.nl/schemas/gds2/afgifte-bestandenlijstselectie/v20170401",
            "@xmlns:v202"    => "http://www.kadaster.nl/schemas/gds2/afgifte-proces/v20170401",
            "soapenv:Body"   => [
                "v20:BestandenlijstOpvragenRequest" => [
                    "v20:verzoek" => [
                        "v201:AfgifteSelectieCriteria" => [
                            'v201:BestandKenmerken'     => [
                                "v201:contractnummer" => "{$configuration['contractNumber']}",
                                'v201:artikelnummer'  => "{$configuration['articleNumber']}",
                            ],
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
     * @param  array $result
     * @return array
     */
    private function getDataUrls(array $result): array
    {
        $locations = [];
        $baseUrls  = $result['soapenv:Body']['v20:BestandenlijstOpvragenResponse']['v20:antwoord']['v202:BaseURLSet']['v203:BaseURL'];

        foreach ($baseUrls as $baseUrl) {
            if ($baseUrl['@type'] === 'certificaat') {
                break;
            }
        }

        $files = $result['soapenv:Body']['v20:BestandenlijstOpvragenResponse']['v20:antwoord']['v204:BestandenLijst']['v204:Afgifte'];
        if ($this->isAssociative($files)) {
            $fileUrl     = $files['ns:digikoppeling-external-datareferences']['ns:data-reference']['ns:transport']['ns:location']['ns:senderUrl']['#'];
            $mime        = $files['ns:digikoppeling-external-datareferences']['ns:data-reference']['ns:content']['@contentType'];
            $locations[] = [
                'url'  => "{$baseUrl['#']}/$fileUrl",
                'mime' => "$mime",
            ];

            return $locations;
        }

        foreach ($files as $file) {
            $fileUrl     = $file['ns:digikoppeling-external-datareferences']['ns:data-reference']['ns:transport']['ns:location']['ns:senderUrl']['#'];
            $mime        = $file['ns:digikoppeling-external-datareferences']['ns:data-reference']['ns:content']['@contentType'];
            $locations[] = [
                'url'  => "{$baseUrl['#']}/$fileUrl",
                'mime' => "$mime",
            ];
        }

        return $locations;

    }//end getDataUrls()


    public function fetchData(array $locations, array $configuration): array
    {
        $source = $this->grService->getSource($configuration['downloadSource'], 'common-gateway/brk-bundle');
        $data   = [];

        foreach ($locations as $location) {
            if ($location['mime'] !== 'application/zip') {
                continue;
            }

            if (str_contains($location['url'], $source->getLocation())) {
                $endpoint               = substr($location['url'], strlen($source->getLocation()));
                $file                   = $this->callService->call($source, $endpoint, 'GET');
                $data[$location['url']] = $this->fshService->decode($file, 'zip');
            }
        }

        return $data;

    }//end fetchData()


    /**
     * Calls the GDS source with configured data
     *
     * @param  array $data          The data for the action.
     * @param  array $configuration The configuration of the action.
     * @return array The data downloaded from the GDS service.
     */
    public function gdsDataHandler(array $data, array $configuration): array
    {
        $source     = $this->grService->getSource($configuration['gdsSource'], 'common-gateway/brk-bundle');
        $lastSynced = new DateTime($configuration['lastSynchronization']);

        $location = $configuration['endpoint'];
        $message  = $this->createRequestMessage($lastSynced, $configuration);

        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'soapenv:Envelope']);
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
