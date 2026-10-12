<?php

namespace Wexample\SymfonyShop\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyShop\Enum\VariationPriceAction;
use Wexample\SymfonyShop\Repository\CartItemRepository;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMoney\Entity\Traits\HasPriceVatTrait;
use Wexample\SymfonyMoney\Entity\Traits\HasQuantityTrait;
use Wexample\SymfonyMoney\Entity\Traits\PricedChildTrait;
use Wexample\SymfonyMoney\Interface\PricedChildInterface;
use Wexample\SymfonyMoney\Interface\PricedParentInterface;
use Wexample\SymfonyMoney\Interface\QuantifiedInterface;
use Wexample\SymfonyMoney\Interface\VatRatedInterface;

/**
 * A product in a cart, priced from a snapshot taken when the product was set.
 *
 * `priceBase` is the snapshot (or the amount chosen for a free-amount product);
 * variations then decide the effective raw price, or override the final price.
 */
#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'cart_item')]
class CartItem extends AbstractEntity implements PricedChildInterface, QuantifiedInterface, VatRatedInterface
{
    use PricedChildTrait;
    use HasQuantityTrait;
    use HasPriceVatTrait;

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

        $this->priceRaw = $raw;
        $this->priceOverridden = $final;

        return $this->updatePriceTotal();
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
