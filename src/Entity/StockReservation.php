<?php

namespace Wexample\SymfonyShop\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyShop\Enum\ReservationStatus;
use Wexample\SymfonyShop\Repository\StockReservationRepository;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * Units of an availability held for a cart item while its cart is being paid.
 */
#[ORM\Entity(repositoryClass: StockReservationRepository::class)]
#[ORM\Table(name: 'cart_stock_reservation')]
#[ORM\Index(columns: ['status', 'expires_at'])]
class StockReservation extends AbstractEntity
{
    #[ORM\ManyToOne(targetEntity: ProductAvailability::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ProductAvailability $availability;

    #[ORM\ManyToOne(targetEntity: CartItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?CartItem $cartItem = null;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $quantity;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ReservationStatus::class)]
    protected ReservationStatus $status = ReservationStatus::Active;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $dateCreated;

    public function __construct()
    {
        parent::__construct();
        $this->dateCreated = new DateTimeImmutable();
    }

    public function getAvailability(): ProductAvailability
    {
        return $this->availability;
    }

    public function setAvailability(ProductAvailability $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    public function getCartItem(): ?CartItem
    {
        return $this->cartItem;
    }

    public function setCartItem(?CartItem $cartItem): static
    {
        $this->cartItem = $cartItem;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function setStatus(ReservationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isHolding(?DateTimeImmutable $now = null): bool
    {
        return ReservationStatus::Confirmed === $this->status
            || (ReservationStatus::Active === $this->status && $this->expiresAt > ($now ?? new DateTimeImmutable()));
    }
}
