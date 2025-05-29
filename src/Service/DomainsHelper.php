<?php

namespace App\Service;

use App\Dto\DomainAddParams;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DomainsHelper
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly string $nginxSitesAvailableDir,
        private readonly string $nginxSitesEnabledDir,
        private readonly string $nginxReloadFile,
        private readonly string $proxyProdHost,
        private readonly string $proxyTestHost,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Lists all files (representing domain configurations) in the nginx sites-available directory.
     *
     * @return array An array of domain filenames.
     */
    public function getAvailableList(): array
    {
        $domains = [];

        try {
            $finder = new Finder();
            $finder->files()->in($this->nginxSitesAvailableDir)->sortByName();

            foreach ($finder as $file) {
                $domains[] = $file->getFilename();
            }
        } catch (\Exception $e) {
            $this->logger->error('Error listing domains in sites-available: ' . $e->getMessage());

            return [];
        }

        return $domains;
    }

    /**
     * Retrieves information about a specific domain configuration file.
     * For simplicity, this example just returns the content of the file.
     * You might want to parse this content into a structured array later.
     *
     * @param string $domain The name of the domain file (e.g., 'yourdomain.com').
     * @return array
     */
    public function getDomainInfo(string $domain): array
    {
        $domainPath = $this->nginxSitesAvailableDir . '/' . $domain;

        if (!$this->filesystem->exists($domainPath)) {
            $this->logger->warning(sprintf('Domain configuration file not found: %s', $domainPath));

            throw new NotFoundHttpException('Domain not exists');
        }

        $confContent = file_get_contents($domainPath);

        return [
            'enabled' => $this->filesystem->exists($this->nginxSitesEnabledDir . '/' . $domain),
            'isTest' => str_contains($confContent, $this->proxyTestHost),
            //'content' => $content,
        ];
    }

    /**
     * Adds a new domain configuration file to the nginx sites-available directory.
     *
     * @param string $domain The name of the domain file (e.g., 'yourdomain.com').
     * @return bool True if the domain was added successfully, false otherwise.
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function addDomain(string $domain, DomainAddParams $params): bool
    {
        $filePath = $this->nginxSitesAvailableDir . '/' . $domain;

        $fileContent = $this->twig->render('nginx/domain.twig', [
            'domain' => $domain,
            'proxyhost' => $params->isTest ? $this->proxyTestHost : $this->proxyProdHost,
        ]);

        try {
            $this->filesystem->dumpFile($filePath, $fileContent);
            $this->logger->info(sprintf('Domain configuration file created: %s', $filePath));

            $this->reloadNginx();

            return true;
        } catch (IOExceptionInterface $e) {
            $this->logger->error(sprintf('Error adding domain %s: %s', $domain, $e->getMessage()));

            return false;
        }
    }

    /**
     * Deletes a domain configuration file from the nginx sites-available directory.
     * It also attempts to remove the symbolic link from sites-enabled if it exists.
     *
     * @param string $domain The name of the domain file (e.g., 'yourdomain.com').
     * @return bool True if the domain was deleted successfully, false otherwise.
     */
    public function deleteDomain(string $domain): bool
    {
        $availablePath = $this->nginxSitesAvailableDir . '/' . $domain;
        $enabledPath = $this->nginxSitesEnabledDir . '/' . $domain;

        try {
            // Remove symbolic link from sites-enabled (if it exists)
            if ($this->filesystem->exists($enabledPath)) {
                $this->filesystem->remove($enabledPath);
                $this->logger->info(sprintf('Symbolic link removed from sites-enabled: %s', $enabledPath));
            } else {
                $this->logger->info(sprintf('No symbolic link found in sites-enabled for: %s', $domain));
            }

            // Remove from sites-available
            if ($this->filesystem->exists($availablePath)) {
                $this->filesystem->remove($availablePath);
                $this->logger->info(sprintf('Domain configuration file removed from sites-available: %s', $availablePath));
            } else {
                $this->logger->warning(sprintf('Domain configuration file not found in sites-available: %s', $availablePath));
            }

            $this->reloadNginx();

            return true;
        } catch (IOExceptionInterface $e) {
            $this->logger->error(sprintf('Error deleting domain %s: %s', $domain, $e->getMessage()));

            return false;
        }
    }

    /**
     * Creates a symbolic link for a domain from sites-available to sites-enabled.
     *
     * @param string $domain The name of the domain file (e.g., 'yourdomain.com').
     * @return bool True if the symbolic link was created successfully, false otherwise.
     */
    public function enableDomain(string $domain): bool
    {
        $availablePath = $this->nginxSitesAvailableDir . '/' . $domain;
        $enabledPath = $this->nginxSitesEnabledDir . '/' . $domain;

        if (!$this->filesystem->exists($availablePath)) {
            $this->logger->warning(sprintf('Cannot enable domain: Configuration file not found in sites-available: %s', $availablePath));

            return false;
        }

        if ($this->filesystem->exists($enabledPath) && is_link($enabledPath)) {
            $this->logger->info(sprintf('Domain %s is already enabled (symbolic link exists).', $domain));

            return true; // Already enabled, consider it a success
        }

        try {
            $this->filesystem->symlink($availablePath, $enabledPath);
            $this->logger->info(sprintf('Symbolic link created for domain %s: %s -> %s', $domain, $availablePath, $enabledPath));

            $this->reloadNginx();

            return true;
        } catch (IOExceptionInterface $e) {
            $this->logger->error(sprintf('Error enabling domain %s: %s', $domain, $e->getMessage()));

            return false;
        }
    }

    /**
     * Removes the symbolic link for a domain from sites-enabled.
     *
     * @param string $domain The name of the domain file (e.g., 'yourdomain.com').
     * @return bool True if the symbolic link was removed successfully, false otherwise.
     */
    public function disableDomain(string $domain): bool
    {
        $enabledPath = $this->nginxSitesEnabledDir . '/' . $domain;

        if (!$this->filesystem->exists($enabledPath) || !is_link($enabledPath)) {
            $this->logger->warning(sprintf('Domain %s is not enabled (no symbolic link found in sites-enabled).', $domain));

            return true; // Not enabled, consider it a success
        }

        try {
            $this->filesystem->remove($enabledPath);
            $this->logger->info(sprintf('Symbolic link removed for domain %s from sites-enabled: %s', $domain, $enabledPath));

            $this->reloadNginx();

            return true;
        } catch (IOExceptionInterface $e) {
            $this->logger->error(sprintf('Error disabling domain %s: %s', $domain, $e->getMessage()));

            return false;
        }
    }

    private function reloadNginx(): void
    {
        file_put_contents($this->nginxReloadFile, '');
    }
}