<?php

namespace App\Controller;

use App\Dto\DomainInfo;
use App\Dto\DomainInList;
use App\Service\DomainsHelper;
use Psr\Log\LoggerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse; // Use JsonResponse for cleaner API responses
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/domains', name: 'domains_')]
class DomainsController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DomainsHelper $domainsHelper,
    ) {}

    #[Route('/', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Successful response',
        content: new OA\JsonContent(
            type: 'array',
            //items: new OA\Items(ref: new Model(type: DomainInList::class))
            items: new OA\Items(type: "string", example: "yourdomain.com")
        )
    )]
    public function list(): JsonResponse
    {
        $list = $this->domainsHelper->getAvailableList();

        return $this->json($list, Response::HTTP_OK);
    }

    #[Route('/{domain}', name: 'get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Show domain info',
        content: new Model(type: DomainInfo::class)
    )]
    public function getDomain(string $domain): JsonResponse
    {
        $data = $this->domainsHelper->getDomainInfo($domain);

        if (empty($data)) {
            return $this->json(['message' => 'Domain not found or could not be retrieved.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/{domain}', name: 'add', methods: ['POST'])]
    public function addDomain(string $domain, Request $request): Response
    {
        if ($this->domainsHelper->addDomain($domain)) {
            return $this->json(['message' => sprintf('Domain %s added successfully.', $domain)], Response::HTTP_CREATED);
        }

        return $this->json(['message' => sprintf('Failed to add domain %s.', $domain)], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/{domain}', name: 'delete', methods: ['DELETE'])]
    public function deleteDomain(string $domain): JsonResponse
    {
        if ($this->domainsHelper->deleteDomain($domain)) {
            return $this->json(['message' => sprintf('Domain %s deleted successfully.', $domain)], Response::HTTP_OK);
        }

        return $this->json(['message' => sprintf('Failed to delete domain %s.', $domain)], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/{domain}/enable', name: 'enable', methods: ['PUT'])]
    public function enableDomain(string $domain): JsonResponse
    {
        if ($this->domainsHelper->enableDomain($domain)) {
            return $this->json(['message' => sprintf('Domain %s enabled successfully.', $domain)], Response::HTTP_OK);
        }

        return $this->json(['message' => sprintf('Failed to enable domain %s. Check logs for details.', $domain)], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/{domain}/disable', name: 'disable', methods: ['PUT'])]
    public function disableDomain(string $domain): JsonResponse
    {
        if ($this->domainsHelper->disableDomain($domain)) {
            return $this->json(['message' => sprintf('Domain %s disabled successfully.', $domain)], Response::HTTP_OK);
        }

        return $this->json(['message' => sprintf('Failed to disable domain %s. Check logs for details.', $domain)], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}