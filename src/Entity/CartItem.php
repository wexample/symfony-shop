<?php

namespace Wexample\SymfonyShop\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMoney\Entity\Traits\HasPriceDiscountTrait;
use Wexample\SymfonyMoney\Entity\Traits\HasPriceVatTrait;
use Wexample\SymfonyMoney\Entity\Traits\HasQuantityTrait;
use Wexample\SymfonyMoney\Entity\Traits\PricedChildTrait;
use Wexample\SymfonyMoney\Helper\RateHelper;
use Wexample\SymfonyMoney\Interface\DiscountedInterface;
use Wexample\SymfonyMoney\Interface\PricedLineInterface;
use Wexample\SymfonyMoney\Interface\PricedParentInterface;
use Wexample\SymfonyShop\Enum\VariationPriceAction;
use Wexample\SymfonyShop\Repository\CartItemRepository;

/**
 * A product in a cart, priced from a snapshot taken when the product was set.
 *
 * `priceBase` is the snapshot (or the amount chosen for a free-amount product);
 * variations then decide the effective raw price, or override the final price.
 *
 * `priceVatDefault` is the product's rate; `priceVat` the one charged to this
 * buyer (see CartService::applyVatRates()). An overridden final price includes
 * the default rate: charged at another one, it is reduced to its base first.
 */
#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'cart_item')]
class CartItem extends AbstractEntity implements PricedLineInterface, DiscountedInterface
{
    use PricedChildTrait;
    use HasQuantityTrait;
    use HasPriceVatTrait;
    use HasPriceDiscountTrait;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Cart $cart = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    protected Product $product;

    #[ORM\ManyToOne(targetEntity: Schedule::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?Schedule $schedule = null;

    /** @var Collection<int, ProductVariation> */
    #[ORM\ManyToMany(targetEntity: ProductVariation::class)]
    #[ORM\JoinTable(name: 'cart_item_variation')]
    protected Collection $variations;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $priceBase = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $priceBaseOverridden = null;

    /** Null on items older than the column: their rate never changed. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $priceVatDefault = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $comment = null;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $position = 0;

    public function __construct()
    {
        parent::__construct();
        $this->variations = new ArrayCollection();
        $this->quantity = 1;
    }

    public function getPriceParent(): ?PricedParentInterface
    {
        return $this->cart;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this->updatePriceTotal();
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * Copies the product's price, VAT and title: later product edits never reach this item.
     */
    public function setProduct(
        Product $product,
        ?int $priceBase = null
    ): static {
        $this->product = $product;
        $this->title = $product->getTitle();
        $this->priceVat = $product->getPriceVat();
        $this->priceVatDefault = $product->getPriceVat();
        $this->priceBase = $priceBase ?? $product->getPriceRaw();
        $this->priceBaseOverridden = $product->getPriceOverridden();

        return $this->applyVariations();
    }

    public function getPriceBase(): ?int
    {
        return $this->priceBase;
    }

    /**
     * The amount chosen by the buyer, for a free-amount product.
     */
    public function setPriceBase(?int $priceBase): static
    {
        $this->priceBase = $priceBase;

        return $this->applyVariations();
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
        }

        return $this->applyVariations();
    }

    public function removeVariation(ProductVariation $variation): static
    {
        $this->variations->removeElement($variation);

        return $this->applyVariations();
    }

    /**
     * Recomputes the effective prices from the base and the chosen variations.
     * Call it after editing a variation's action or amount.
     */
    public function applyVariations(): static
    {
        $raw = $this->priceBase;
        $final = $this->priceBaseOverridden;

        foreach ($this->variations as $variation) {
            match ($variation->getPriceAction()) {
                VariationPriceAction::PriceRaw => $raw = $variation->getAmount(),
                VariationPriceAction::PriceFinal => $final = $variation->getAmount(),
                VariationPriceAction::None => null,
            };
        }

        if (null !== $final && $this->getPriceVatDefault() !== $this->priceVat) {
            $base = RateHelper::extractBase($final, $this->getPriceVatDefault());
            $final = $base + RateHelper::rateOf($base, $this->priceVat);
        }

        $this->priceRaw = $raw;
        $this->priceOverridden = $final;

        return $this->updatePriceTotal();
    }

    public function getPriceVatDefault(): int
    {
        return $this->priceVatDefault ?? $this->priceVat;
    }

    /**
     * Charges the line at the rate decided for its buyer, from its default one.
     */
    public function applyVatRate(int $rate): static
    {
        $this->priceVatDefault = $this->getPriceVatDefault();
        $this->priceVat = $rate;

        return $this->applyVariations();
    }

    public function isGoods(): bool
    {
        return $this->product->hasShipping();
    }

    public function getAccountNumber(): ?string
    {
        return $this->product->getAccountNumber();
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
