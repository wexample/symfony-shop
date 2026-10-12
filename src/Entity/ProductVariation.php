<?php

namespace Wexample\SymfonyShop\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyShop\Enum\VariationPriceAction;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * An option of a product (presenter / spectator, size), possibly changing the price.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cart_product_variation')]
class ProductVariation extends AbstractEntity
{
    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected Product $product;

    /** The option family: "role", "size". */
    #[ORM\Column(type: Types::STRING, length: 100)]
    protected string $name;

    /** The option chosen in that family: "presenter", "XL". */
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $value;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: VariationPriceAction::class)]
    protected VariationPriceAction $priceAction = VariationPriceAction::None;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $amount = null;

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getPriceAction(): VariationPriceAction
    {
        return $this->priceAction;
    }

    /**
     * Never touches the product: network's version recomputed the product price
     * here and crashed when no product was set yet.
     */
    public function setPriceAction(
        VariationPriceAction $priceAction,
        ?int $amount = null
    ): static {
        $this->priceAction = $priceAction;
        $this->amount = $amount;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }
}
