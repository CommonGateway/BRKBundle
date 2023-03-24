<?php
/**
 * The BRK handler
 * This handler is used to convert xml files from the BRK fileSystem to ObjectEntities.
 *
 * @author  Wilco Louwerse <wilco@conduction.nl>, Nova Bank <nova@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\BRKBundle\ActionHandler;

use CommonGateway\BRKBundle\Service\BRKService;

/**
 * Convert xml file from the BRK fileSystem to ObjectEntities.
 */
class BRKHandler
{
    /**
     * @var BRKService The BRKService.
     */
    private BRKService $BRKService;
    
    /**
     * @param BRKService $BRKService The BRKService.
     */
    public function __construct(BRKService $BRKService)
    {
        $this->BRKService = $BRKService;
    }//end __construct()
    
    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://brk.commonground.nu/ActionHandler/BRKHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'BRKHandler',
            'description' => 'This handler converts a xml file from the BRK fileSystem to ObjectEntities or updates existing ObjectEntities',
            'required'    => [],
            'properties'  => [],
        ];
    }

    /**
     * This function runs the service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->BRKService->BRKHandler($data, $configuration);
    }
}
