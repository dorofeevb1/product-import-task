<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Product\Entity\Product;
use App\Domain\Product\Repository\ProductRepository;

final class ProductCrudIntegrationTest extends IntegrationTestCase
{
    public function testCrudLifecycleForProduct(): void
    {
        $repository = new ProductRepository($this->entityManager);

        $product = new Product('SKU-CRUD', 'CRUD Name', 'CRUD Desc', '99.99', '15.00');
        $repository->save($product);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $stored = $repository->findByExternalCode('SKU-CRUD');
        self::assertNotNull($stored);
        self::assertSame('CRUD Name', $stored->getName());

        $stored->update('CRUD Updated', 'CRUD Desc 2', '109.99', '18.00');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $updated = $repository->findByExternalCode('SKU-CRUD');
        self::assertNotNull($updated);
        self::assertSame('CRUD Updated', $updated->getName());

        $this->entityManager->remove($updated);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($repository->findByExternalCode('SKU-CRUD'));
    }
}
