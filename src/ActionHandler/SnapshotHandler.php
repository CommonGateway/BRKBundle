<?php
/**
 * The BRK handler
 * This handler is used to convert xml files from the BRK fileSystem to ObjectEntities.
 *
 * @author  Wilco Louwerse <wilco@conduction.nl>, Nova Bank <nova@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\BRKBundle\ActionHandler;

use CommonGateway\BRKBundle\Service\BrkService;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;

/**
 * Convert xml file from the BRK fileSystem to ObjectEntities.
 */
class SnapshotHandler implements ActionHandlerInterface
{

    /**
     * The brk service
     *
     * @var BrkService
     */
    private BrkService $brkService;


    /**
     * The class constructor
     *
     * @param BrkService $brkService The brk service
     */
    public function __construct(BrkService $brkService)
    {
        $this->brkService = $brkService;

    }//end __construct()


    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://brk.commonground.nu/ActionHandler/SnapshotHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'Brk ActionHandler',
            'description' => 'This handler converts a single snapshot into multiple BRK objects',
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
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->brkService->snapshotHandler($data, $configuration);

    }//end run()


}//end class
