<?php

namespace App\Service;

class DomainsHelper
{
    public function __construct(
        private readonly string $nginxSitesAvailableDir,
        private readonly string $nginxSitesEnabledDir,
    ) {
    }

    public function getAvailableList(): array
    {
        return [];
    }

    public function getDomainInfo(): array
    {
        return [];
    }

    public function addDomain(): void
    {
        ;
    }

    public function deleteDomain(): void
    {
        ;
    }

}