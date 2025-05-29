<?php

namespace App\Controller;

use App\Dto\DomainAddParams;
use App\Dto\DomainInfo;
use App\Service\DomainsHelper;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[Route('/api/v1/domains', name: 'domains_')]
class DomainsController extends AbstractController
{
    public function __construct(
        private readonly DomainsHelper $domainsHelper,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Get list of all available domains',
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
    #[OA\Response(
        response: Response::HTTP_CREATED,
        description: 'Add new domain'
    )]
    #[OA\RequestBody(
        content: new Model(type: DomainAddParams::class)
    )]
    public function addDomain(
        string $domain,
        Request $request
    ): JsonResponse {
        $jsonContent = $request->getContent();

        if (empty($jsonContent)) {
            $params = new DomainAddParams();
        } else {
            try {
                /** @var DomainAddParams $domainParams */
                $params = $this->serializer->deserialize(
                    $jsonContent,
                    DomainAddParams::class,
                    'json'
                );
            } catch (NotEncodableValueException $e) {
                $this->logger->error('JSON deserialization error: ' . $e->getMessage());

                return $this->json(['message' => 'Invalid JSON format.'], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($this->domainsHelper->addDomain($domain, $params)) {
            return $this->json(['message' => sprintf('Domain %s added successfully.', $domain)], Response::HTTP_CREATED);
        }

        return $this->json(['message' => sprintf('Failed to add domain %s.', $domain)], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/{domain}', name: 'delete', methods: ['DELETE'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Delete domain'
    )]
    public function deleteDomain(string $domain): JsonResponse
    {
        if ($this->domainsHelper->deleteDomain($domain)) {
            return $this->json(['message' => sprintf('Domain %s deleted successfully.', $domain)], Response::HTTP_OK);
        }

        return $this->json(['message' => sprintf('Failed to delete domain %s.', $domain)], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/{domain}/enable', name: 'enable', methods: ['PUT'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Enable domain'
    )]
    public function enableDomain(string $domain): JsonResponse
    {
        if ($this->domainsHelper->enableDomain($domain)) {
            return $this->json(['message' => sprintf('Domain %s enabled successfully.', $domain)], Response::HTTP_OK);
        }

        return $this->json(['message' => sprintf('Failed to enable domain %s. Check logs for details.', $domain)], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/{domain}/disable', name: 'disable', methods: ['PUT'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Disable domain'
    )]
    public function disableDomain(string $domain): JsonResponse
    {
        if ($this->domainsHelper->disableDomain($domain)) {
            return $this->json(['message' => sprintf('Domain %s disabled successfully.', $domain)], Response::HTTP_OK);
        }

        return $this->json(['message' => sprintf('Failed to disable domain %s. Check logs for details.', $domain)], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}