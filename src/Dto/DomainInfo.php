<?php

namespace App\Dto;

use OpenApi\Attributes as OA;

class DomainInfo
{
    #[OA\Property(
        description: 'Domain is enabled',
        default: false,
        example: true,
    )]
    public bool $enabled;

    #[OA\Property(
        description: 'Is test domain - all requests will be served to the development host',
        default: false,
        example: true,
    )]
    public bool $isTest;

}