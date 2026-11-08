<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'uniq_products_external_code', columns: ['external_code'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'external_code', type: 'string', length: 191, unique: true)]
    private string $externalCode;

    #[ORM\Column(type: 'string', length: 500)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $price;

    /**
     * Not part of the visible product card, but required to calculate the discount.
     */
    #[ORM\Column(name: 'purchase_price', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $discount = null;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductAttribute::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductImage::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $externalCode, string $name, string $price)
    {
        $this->externalCode = $externalCode;
        $this->name = $name;
        $this->price = $price;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Doctrine hydrates the identifier through reflection; this setter also
     * lets fixtures and tests create deterministic records.
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function setExternalCode(string $externalCode): self
    {
        $this->externalCode = $externalCode;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(?string $purchasePrice): self
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function setDiscount(?string $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(ProductAttribute $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
            $attribute->setProduct($this);
        }

        return $this;
    }

    public function clearAttributes(): self
    {
        foreach ($this->attributes as $attribute) {
            $attribute->setProduct(null);
        }

        $this->attributes->clear();

        return $this;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }

        return $this;
    }

    public function clearImages(): self
    {
        foreach ($this->images as $image) {
            $image->setProduct(null);
        }

        $this->images->clear();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
