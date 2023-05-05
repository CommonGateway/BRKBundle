<?php
/**
 * A BRK Handler to provide event driven business logic for the brk plugin.
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\BRKBundle\ActionHandler;

use CommonGateway\BRKBundle\ActionHandler\CacheException;
use CommonGateway\BRKBundle\ActionHandler\ComponentException;
use CommonGateway\BRKBundle\ActionHandler\GatewayException;
use CommonGateway\BRKBundle\ActionHandler\InvalidArgumentException;
use CommonGateway\BRKBundle\Service\BrkService;
use CommonGateway\BRKBundle\Service\GdsService;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;

class GdsHandler implements ActionHandlerInterface
{

    /**
     * The GDS service.
     *
     * @var GdsService
     */
    private GdsService $gdsService;


    /**
     * The class constructor
     *
     * @param BrkService $gdsService The BRK service.
     */
    public function __construct(GdsService $gdsService)
    {
        $this->gdsService = $gdsService;

    }//end __construct()


    /**
     * This function returns the requered configuration as a
     * [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://example.com/ActionHandler/BRKHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'BRK Action',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [
                'gdsSource'           => [
                    'type'        => 'string',
                    'description' => 'the default gds source',
                    'example'     => 'https://brk.commonground.nu/source/gdsAfgifte.source.json',
                    'required'    => true,
                ],
                'downloadSource'      => [
                    'type'        => 'string',
                    'description' => 'the default download source',
                    'example'     => 'https://brk.commonground.nu/source/gdsDownload.source.json',
                    'required'    => true,
                ],
                'endpoint'            => [
                    'type'        => 'string',
                    'description' => 'the default endpoint on the gds source',
                    'example'     => '',
                    'required'    => true,
                ],
                'lastSynchronization' => [
                    'type'        => 'string',
                    'description' => 'the date of the last synchronization',
                    'example'     => '2023-01-01 00:00:00',
                    'required'    => true,
                ],
                'contractNumber'      => [
                    'type'        => 'string',
                    'description' => 'The contract number to request data for',
                    'example'     => '0000000002',
                    'required'    => true,
                ],
                'articleNumber'       => [
                    'type'        => 'string',
                    'description' => 'The article number to request data for',
                    'example'     => '3',
                    'required'    => true,
                ],
                'test'                => [
                    'type'        => 'boolean',
                    'description' => 'Whether the call is in test mode',
                    'example'     => true,
                    'required'    => true,
                ],

            ],
        ];

    }//end getConfiguration()


    /**
     * This function runs the service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->gdsService->gdsDataHandler($data, $configuration);

    }//end run()


}//end class
