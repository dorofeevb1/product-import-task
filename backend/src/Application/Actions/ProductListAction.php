<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Domain\Product\Repository\ProductRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductListAction
{
    public function __construct(private readonly ProductRepository $repository)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($query['limit'] ?? 20)));

        [$items, $total] = $this->repository->findPaged(
            $page,
            $limit,
            $query['name'] ?? null,
            isset($query['priceMin']) ? (float) $query['priceMin'] : null,
            isset($query['priceMax']) ? (float) $query['priceMax'] : null
        );

        $payload = [
            'items' => array_map(static fn ($p) => [
                'id' => $p->getId(),
                'externalCode' => $p->getExternalCode(),
                'name' => $p->getName(),
                'description' => $p->getDescription(),
                'price' => (float) $p->getPrice(),
                'discount' => (float) $p->getDiscount(),
            ], $items),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ];

        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
