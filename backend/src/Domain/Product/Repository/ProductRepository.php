<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

use App\Domain\Product\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

final class ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->entityManager->getRepository(Product::class)->findOneBy(['externalCode' => $externalCode]);
    }

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
    }

    public function findPaged(int $page, int $limit, ?string $name, ?float $priceMin, ?float $priceMax): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p');

        if ($name !== null && $name !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :name')->setParameter('name', '%' . mb_strtolower($name) . '%');
        }

        if ($priceMin !== null) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $priceMin);
        }

        if ($priceMax !== null) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $priceMax);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [$items, $total];
    }

    public function findWithRelations(int $id): ?Product
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p, a, i')
            ->from(Product::class, 'p')
            ->leftJoin('p.attributes', 'a')
            ->leftJoin('p.images', 'i')
            ->where('p.id = :id')
            ->setParameter('id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
