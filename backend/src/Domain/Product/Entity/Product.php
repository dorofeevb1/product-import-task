<?php

declare(strict_types=1);

namespace App\Domain\Product\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'uniq_products_external_code', columns: ['external_code'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 128, name: 'external_code')]
    private string $externalCode;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $discount;

    #[ORM\OneToMany(targetEntity: ProductAttribute::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributes;

    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    public function __construct(string $externalCode, string $name, string $description, string $price, string $discount)
    {
        $this->externalCode = $externalCode;
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function update(string $name, string $description, string $price, string $discount): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
    }

    public function clearAttributes(): void
    {
        $this->attributes->clear();
    }

    public function clearImages(): void
    {
        $this->images->clear();
    }

    public function addAttribute(ProductAttribute $attribute): void
    {
        $this->attributes->add($attribute);
    }

    public function addImage(ProductImage $image): void
    {
        $this->images->add($image);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getDiscount(): string
    {
        return $this->discount;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }
}
