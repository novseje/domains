<?php

namespace App\Controller;

use App\Service\DomainsHelper;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/v1/domains', name: 'domains_')]
class DomainsController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private readonly DomainsHelper $domainsHelper,
    ) {}

    #[Route('/', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $list = $this->domainsHelper->getAvailableList();

        return $this->json($list, Response::HTTP_OK);
    }

    #[Route('/{domain}', name: 'get', methods: ['GET'])]
    public function getDomain(Request $request): Response
    {
        $data = $this->domainsHelper->getDomainInfo();

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/{domain}', name: 'add', methods: ['POST'])]
    public function addDomain(Request $request): Response
    {
        $this->domainsHelper->addDomamin();

        return new Response('OK', 200);
    }

    #[Route('/{domain}', name: 'delete', methods: ['DELETE'])]
    public function deleteDomain(Request $request): Response
    {
        $this->domainsHelper->deleteDomain();

        return new Response('OK', 201);
    }



}