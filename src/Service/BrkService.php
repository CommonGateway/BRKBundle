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
     * @SuppressWarnings('unused') Required by Handler Interface.
     * @return                     array
     */
    public function BrkHandler(array $data, array $configuration): array
    {
        return ['response' => 'Hello. Your BRKBundle works'];

    }//end BrkHandler()


}//end class
