<?php

declare(strict_types=1);

namespace App\Domain\Product\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_images')]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'text')]
    private string $url;

    #[ORM\Column(type: 'text')]
    private string $path;

    public function __construct(Product $product, string $url, string $path)
    {
        $this->product = $product;
        $this->url = $url;
        $this->path = $path;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
