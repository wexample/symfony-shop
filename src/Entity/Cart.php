<?php

namespace Wexample\SymfonyCart\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyCart\Enum\CartStatus;
use Wexample\SymfonyCart\Exception\CartNotEditableException;
use Wexample\SymfonyCart\Repository\CartRepository;
use Wexample\SymfonyGeo\Entity\AddressSnapshot;
use Wexample\SymfonyGeo\Interface\PostalAddressInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMoney\Entity\Traits\HasPriceCurrencyTrait;
use Wexample\SymfonyMoney\Entity\Traits\HasPriceDiscountTrait;
use Wexample\SymfonyMoney\Entity\Traits\PricedParentTrait;
use Wexample\SymfonyMoney\Interface\DiscountedInterface;
use Wexample\SymfonyMoney\Interface\PricedParentInterface;
use Wexample\SymfonyPayment\Entity\Payment;
use Wexample\SymfonyPayment\Interface\PayableInterface;

/**
 * A cart may exist before its owner does: anonymous checkouts attach the owner
 * later. `ownerReference` is the host's identifier of the buyer (a user id).
 *
 * Items only change while the cart is opened; CartService enforces it, and the
 * entity refuses too.
 */
#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\Table(name: 'cart')]
#[ORM\Index(columns: ['status', 'date_updated'])]
class Cart extends AbstractEntity implements PricedParentInterface, DiscountedInterface, PayableInterface
{
    use PricedParentTrait;
    use HasPriceDiscountTrait;
    use HasPriceCurrencyTrait;

    public const string PAYABLE_TYPE = 'cart';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: CartStatus::class)]
    protected CartStatus $status = CartStatus::Opened;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    protected ?string $ownerReference = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $customerEmail = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $customerName = null;

    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    protected Collection $items;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?Payment $payment = null;

    // The addresses given at checkout, written down as they were: an order
    // keeps them whatever becomes of the buyer's own.
    #[ORM\Embedded(class: AddressSnapshot::class, columnPrefix: 'billing_address_')]
    protected AddressSnapshot $billingAddress;

    #[ORM\Embedded(class: AddressSnapshot::class, columnPrefix: 'shipping_address_')]
    protected AddressSnapshot $shippingAddress;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $dateCreated;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $dateUpdated;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $datePaid = null;

    #[ORM\Column(type: Types::JSON)]
    protected array $metadata = [];

    public function __construct()
    {
        parent::__construct();
        $this->items = new ArrayCollection();
        $this->billingAddress = new AddressSnapshot();
        $this->shippingAddress = new AddressSnapshot();
        $this->dateCreated = new DateTimeImmutable();
        $this->dateUpdated = $this->dateCreated;
        $this->priceTotal = 0;
    }

    public static function getPayableType(): string
    {
        return self::PAYABLE_TYPE;
    }

    public function getPayableId(): string
    {
        return (string) $this->getId();
    }

    public function getPayableAmount(): int
    {
        return $this->calcPriceFinal();
    }

    public function getPayableCurrencyCode(): string
    {
        return $this->getCurrencyCode();
    }

    public function getPayableDescription(): ?string
    {
        $titles = $this->items->map(fn (CartItem $item) => $item->getTitle())->toArray();

        return [] === $titles ? null : implode(', ', array_unique($titles));
    }

    public function getPricedChildren(): iterable
    {
        return $this->items;
    }

    public function getStatus(): CartStatus
    {
        return $this->status;
    }

    /**
     * @internal Use CartService / CartCheckoutService.
     */
    public function setStatus(CartStatus $status): static
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function assertEditable(): void
    {
        if (! $this->isEditable()) {
            throw new CartNotEditableException($this);
        }
    }

    public function getOwnerReference(): ?string
    {
        return $this->ownerReference;
    }

    public function setOwnerReference(?string $ownerReference): static
    {
        $this->ownerReference = $ownerReference;

        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): static
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): static
    {
        $this->customerName = $customerName;

        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(CartItem $item): static
    {
        $this->assertEditable();

        if (! $this->items->contains($item)) {
            $item->setPosition($this->items->count());
            $this->items->add($item);
            $item->setCart($this);
        }

        return $this->touch();
    }

    public function removeItem(CartItem $item): static
    {
        $this->assertEditable();
        $this->items->removeElement($item);
        $this->updatePriceTotal();

        return $this->touch();
    }

    public function hasShipping(): bool
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()->hasShipping()) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * Null while none was given.
     */
    public function getBillingAddress(): ?AddressSnapshot
    {
        return $this->billingAddress->isEmpty() ? null : $this->billingAddress;
    }

    /**
     * Written down as `$address` reads now.
     */
    public function setBillingAddress(?PostalAddressInterface $address): static
    {
        $this->billingAddress = $address ? AddressSnapshot::fromAddress($address) : new AddressSnapshot();

        return $this;
    }

    public function getShippingAddress(): ?AddressSnapshot
    {
        return $this->shippingAddress->isEmpty() ? null : $this->shippingAddress;
    }

    public function setShippingAddress(?PostalAddressInterface $address): static
    {
        $this->shippingAddress = $address ? AddressSnapshot::fromAddress($address) : new AddressSnapshot();

        return $this;
    }

    public function getDateCreated(): DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function setDateCreated(DateTimeImmutable $dateCreated): static
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateUpdated(): DateTimeImmutable
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated(DateTimeImmutable $dateUpdated): static
    {
        $this->dateUpdated = $dateUpdated;

        return $this;
    }

    public function touch(): static
    {
        $this->dateUpdated = new DateTimeImmutable();

        return $this;
    }

    public function getDatePaid(): ?DateTimeImmutable
    {
        return $this->datePaid;
    }

    public function setDatePaid(?DateTimeImmutable $datePaid): static
    {
        $this->datePaid = $datePaid;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }
}
