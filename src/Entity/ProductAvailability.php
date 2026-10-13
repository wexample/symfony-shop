<?php

namespace Wexample\SymfonyShop\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyShop\Repository\ProductAvailabilityRepository;

/**
 * How many units of a product can be sold, possibly for one schedule and one
 * variation only. A null quantity means unlimited. The row is kept when sold out:
 * what is left is computed from reservations, never by deleting.
 */
#[ORM\Entity(repositoryClass: ProductAvailabilityRepository::class)]
#[ORM\Table(name: 'cart_product_availability')]
class ProductAvailability extends AbstractEntity
{
    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected Product $product;

    #[ORM\ManyToOne(targetEntity: Schedule::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    protected ?Schedule $schedule = null;

    #[ORM\ManyToOne(targetEntity: ProductVariation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    protected ?ProductVariation $variation = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $quantity = null;

    /** At or below this many units left, a sale raises LowStockEvent. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $lowStockThreshold = null;

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getSchedule(): ?Schedule
    {
        return $this->schedule;
    }

    public function setSchedule(?Schedule $schedule): static
    {
        $this->schedule = $schedule;

        return $this;
    }

    public function getVariation(): ?ProductVariation
    {
        return $this->variation;
    }

    public function setVariation(?ProductVariation $variation): static
    {
        $this->variation = $variation;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getLowStockThreshold(): ?int
    {
        return $this->lowStockThreshold;
    }

    public function setLowStockThreshold(?int $lowStockThreshold): static
    {
        $this->lowStockThreshold = $lowStockThreshold;

        return $this;
    }

    public function isUnlimited(): bool
    {
        return null === $this->quantity;
    }
}
