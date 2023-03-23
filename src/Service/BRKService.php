<?php

// src/Service/BRKService.php
namespace CommonGateway\BRKBundle\src\Service;

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

    }//end BRKHandler()


}//end class
