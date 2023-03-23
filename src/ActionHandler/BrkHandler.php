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

class BrkHandler
{


    /**
     * The BRK service.
     *
     * @var BrkService
     */
    private BrkService $brkService;


    /**
     * The class constructor
     *
     * @param BrkService $brkService The BRK service.
     */
    public function __construct(BrkService $brkService)
    {
        $this->brkService = $brkService;

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
            'properties'  => [],
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
        return $this->brkService->BrkHandler($data, $configuration);

    }//end run()


}//end class
