<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Actions\ProductListAction;
use App\Domain\Product\Entity\Product;
use App\Domain\Product\Repository\ProductRepository;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class ProductListActionTest extends IntegrationTestCase
{
    public function testListActionReturnsCorrectPaginationAndFilters(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $price = (string) (50 + $i);
            $name = $i <= 10 ? 'Alpha ' . $i : 'Beta ' . $i;
            $product = new Product('SKU-' . $i, $name, 'Desc ' . $i, $price, '10.00');
            $this->entityManager->persist($product);
        }
        $this->entityManager->flush();

        $action = new ProductListAction(new ProductRepository($this->entityManager));
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/products')
            ->withQueryParams([
                'page' => '2',
                'limit' => '5',
                'name' => 'Alpha',
                'priceMin' => '52',
                'priceMax' => '65',
            ]);
        $response = new Response();
        $response = $action($request, $response);

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $payload['page']);
        self::assertSame(5, $payload['limit']);
        self::assertSame(2, $payload['totalPages']);
        self::assertCount(4, $payload['items']);
        foreach ($payload['items'] as $item) {
            self::assertStringContainsString('Alpha', $item['name']);
            self::assertGreaterThanOrEqual(52, $item['price']);
            self::assertLessThanOrEqual(65, $item['price']);
        }
    }
}
