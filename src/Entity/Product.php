<?php

namespace Wexample\SymfonyShop\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMoney\Entity\Traits\HasPriceCurrencyTrait;
use Wexample\SymfonyMoney\Entity\Traits\HasPriceVatTrait;
use Wexample\SymfonyMoney\Entity\Traits\PricedSingleTrait;
use Wexample\SymfonyMoney\Interface\PricedInterface;
use Wexample\SymfonyMoney\Interface\VatRatedInterface;
use Wexample\SymfonyShop\Enum\ProductStatus;
use Wexample\SymfonyShop\Repository\ProductRepository;

/**
 * Something sold through a cart. Its price is copied into each cart item when added,
 * so editing a product never changes existing carts.
 *
 * `type` selects the handlers run when an item of this product is paid
 * (CartItemPaidHandlerInterface), e.g. "membership", "donation", "ticket".
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'cart_product')]
class Product extends AbstractEntity implements PricedInterface, VatRatedInterface
{
    /** The type whose files are delivered once paid (DigitalDeliveryHandler). */
    public const string TYPE_DIGITAL = 'digital';

    use PricedSingleTrait;
    use HasPriceVatTrait;
    use HasPriceCurrencyTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $title;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    protected string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    protected string $type = 'default';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ProductStatus::class)]
    protected ProductStatus $status = ProductStatus::Active;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $hasShipping = false;

    /** The buyer chooses the amount (donation, free contribution). */
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $freeAmount = false;

    /** How many items of this product one cart may hold; null for no limit. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $maxPerCart = null;

    /** Revenue account to book its sales on, when it differs from the default. */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    protected ?string $accountNumber = null;

    #[ORM\Column(type: Types::JSON)]
    protected array $metadata = [];

    /** @var Collection<int, ProductVariation> */
    #[ORM\OneToMany(targetEntity: ProductVariation::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    protected Collection $variations;

    /** @var Collection<int, ProductAvailability> */
    #[ORM\OneToMany(targetEntity: ProductAvailability::class, mappedBy: 'product', cascade: ['persist'])]
    protected Collection $availabilities;

    /** @var Collection<int, Media> What a digital product delivers once paid: restricted media, downloaded through grants. */
    #[ORM\ManyToMany(targetEntity: Media::class)]
    #[ORM\JoinTable(name: 'shop_product_file')]
    protected Collection $files;

    public function __construct()
    {
        parent::__construct();
        $this->variations = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->files = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ProductStatus
    {
        return $this->status;
    }

    public function setStatus(ProductStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isSalable(): bool
    {
        return ProductStatus::Active === $this->status;
    }

    public function hasShipping(): bool
    {
        return $this->hasShipping;
    }

    public function setHasShipping(bool $hasShipping): static
    {
        $this->hasShipping = $hasShipping;

        return $this;
    }

    public function isFreeAmount(): bool
    {
        return $this->freeAmount;
    }

    public function setFreeAmount(bool $freeAmount): static
    {
        $this->freeAmount = $freeAmount;

        return $this;
    }

    public function getMaxPerCart(): ?int
    {
        return $this->maxPerCart;
    }

    public function setMaxPerCart(?int $maxPerCart): static
    {
        $this->maxPerCart = $maxPerCart;

        return $this;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(?string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;

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

    /**
     * @return Collection<int, ProductVariation>
     */
    public function getVariations(): Collection
    {
        return $this->variations;
    }

    public function addVariation(ProductVariation $variation): static
    {
        if (! $this->variations->contains($variation)) {
            $this->variations->add($variation);
            $variation->setProduct($this);
        }

        return $this;
    }

    public function removeVariation(ProductVariation $variation): static
    {
        $this->variations->removeElement($variation);

        return $this;
    }

    /**
     * @return Collection<int, ProductAvailability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(ProductAvailability $availability): static
    {
        if (! $this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setProduct($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    /**
     * A file sold is restricted: the library's readers no longer open it.
     */
    public function addFile(Media $media): static
    {
        if (! $this->files->contains($media)) {
            $this->files->add($media->setRestricted(true));
        }

        return $this;
    }

    public function removeFile(Media $media): static
    {
        $this->files->removeElement($media);

        return $this;
    }
}
