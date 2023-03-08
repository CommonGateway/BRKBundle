<?php

// src/Service/BRKService.php

namespace CommonGateway\BRKBundle\Service;

class BRKService
{

    /*
     * Returns a welcoming string
     * 
     * @return array 
     */
    public function BRKHandler(array $data, array $configuration): array
    {
        return ['response' => 'Hello. Your BRKBundle works'];
    }
}
