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
use DateTime;
use Symfony\Component\Serializer\Encoder\XmlEncoder;


class GdsService
{

    private CallService $callService;


    public function __construct(CallService $callService)
    {
        $this->callService = $callService;

    }//end __construct()


    /**
     * Creates the content for a soap message to the GDS download service.
     *
     * @param DateTime $lastSynced The date and time the data has last been synced.
     * @param bool     $test       Whether or not the call is a test call.
     *
     * @return array The resulting request message.
     */
    private function createRequestMessage(DateTime $lastSynced, bool $test): array
    {
        $now            = new \DateTime();
        $notYetReported = 'true';

        if ($test === true) {
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
                                "v201:contractnummer" => '0000000002',
                                'v201:artikelnummer'  => '3',
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
        $urls     = [];
        $baseUrls = $result['soapenv:Body']['v20:BestandenlijstOpvragenResponse']['v20:antwoord']['v202:BaseURLSet']['v203:BaseURL'];

        foreach ($baseUrls as $baseUrl) {
            if ($baseUrl['@type'] === 'certificaat') {
                break;
            }
        }

        $files = $result['soapenv:Body']['v20:BestandenlijstOpvragenResponse']['v20:antwoord']['v204:BestandenLijst']['v204:Afgifte'];
        if ($this->isAssociative($files)) {
            $fileUrl = $files['ns:digikoppeling-external-datareferences']['ns:data-reference']['ns:transport']['ns:location']['ns:senderUrl']['#'];
            $urls[]  = "{$baseUrl['#']}/$fileUrl";

            return $urls;
        }

        foreach ($files as $file) {
            $fileUrl = $file['ns:digikoppeling-external-datareferences']['ns:data-reference']['ns:transport']['ns:location']['ns:senderUrl']['#'];
            $urls[]  = "{$baseUrl['#']}/$fileUrl";
        }

        return $urls;

    }//end getDataUrls()


    /**
     * @param  Source   $source
     * @param  string   $location
     * @param  DateTime $lastSynced
     * @param  bool     $test
     * @return array
     */
    public function getData(Source $source, string $location, DateTime $lastSynced, bool $test=false): array
    {
        $message = $this->createRequestMessage($lastSynced, $test);

        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'soapenv:Envelope']);
        $body       = $xmlEncoder->encode($message, 'xml');
        $response   = $this->callService->call($source, $location, 'POST', ['body' => $body]);
        $result     = $this->callService->decodeResponse($source, $response);

        return $this->getDataUrls($result);

    }//end getData()


}//end class
