<?php
/**
 * A service to provide business logic for the BRK bundle if necessary.
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\BRKBundle\Service;

class BrkService
{


    /**
     * Returns a welcoming string
     *
     * @param array $data          The data to process.
     * @param array $configuration The configuration to process the data with.
     *
     * @return array
     *
     * @SuppressWarnings("unused") Required by Handler Interface.
     */
    public function brkHandler(array $data, array $configuration): array
    {
        return ['response' => 'Hello. Your BRKBundle works'];

    }//end brkHandler()


}//end class
