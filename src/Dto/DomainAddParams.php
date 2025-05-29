<?php

namespace App\Dto;

use OpenApi\Attributes as OA;

class DomainAddParams
{
    #[OA\Property(
        description: 'Set as test domain - all requests will be served to the  development host.',
        example: true,
        default: false
    )]
    public bool $isTest = false;
}