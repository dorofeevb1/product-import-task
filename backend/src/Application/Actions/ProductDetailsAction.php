<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Domain\Product\Repository\ProductRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

final class ProductDetailsAction
{
    public function __construct(private readonly ProductRepository $repository)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->repository->findWithRelations((int) $args['id']);
        if ($product === null) {
            throw new HttpNotFoundException($request, 'Product not found');
        }

        $payload = [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => (float) $product->getPrice(),
            'discount' => (float) $product->getDiscount(),
            'attributes' => array_map(static fn ($a) => ['key' => $a->getKey(), 'value' => $a->getValue()], $product->getAttributes()->toArray()),
            'images' => array_map(static fn ($i) => ['url' => $i->getUrl(), 'path' => $i->getPath()], $product->getImages()->toArray()),
        ];

        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
