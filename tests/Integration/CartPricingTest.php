<?php

namespace Wexample\SymfonyShop\Tests\Integration;

use Wexample\SymfonyShop\Entity\ProductVariation;
use Wexample\SymfonyShop\Enum\VariationPriceAction;

class CartPricingTest extends AbstractCartTestCase
{
    public function testProductPriceThroughTheCart(): void
    {
        $product = $this->product(25000, 2000);
        $cart = $this->carts()->create();
        $item = $this->carts()->addProduct($cart, $product);

        $this->assertSame(30000, $item->calcPriceFinal());
        $this->assertSame(30000, $cart->calcPriceFinal());

        $this->carts()->setQuantity($item, 2);
        $this->assertSame(50000, $cart->calcPriceSubTotal());
        $this->assertSame(10000, $cart->calcPriceVat());
        $this->assertSame(60000, $cart->calcPriceFinal());
        $this->assertSame(60000, $cart->getPriceTotal());
    }

    public function testSnapshot(): void
    {
        $product = $this->product(10000, 2000);
        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $product);

        $product->setPriceRaw(99900);
        $this->em()->flush();
        $this->carts()->recompute($cart);

        $this->assertSame(12000, $cart->calcPriceFinal());
    }

    public function testVariations(): void
    {
        $product = $this->product(500, 1000, slug: 'show');
        $presenter = (new ProductVariation())->setName('role')->setValue('presenter')
            ->setPriceAction(VariationPriceAction::PriceFinal, 200);
        $spectator = (new ProductVariation())->setName('role')->setValue('spectator')
            ->setPriceAction(VariationPriceAction::PriceRaw, 0);
        $product->addVariation($presenter)->addVariation($spectator);
        $this->em()->flush();

        $cart = $this->carts()->create();
        $item = $this->carts()->addProduct($cart, $product, variations: [$presenter]);
        $this->assertSame(200, $cart->calcPriceFinal());

        $item->removeVariation($presenter)->addVariation($spectator);
        $this->assertSame(0, $cart->calcPriceFinal());

        // The raw-price action keeps VAT applying.
        $spectator->setPriceAction(VariationPriceAction::PriceRaw, 200);
        $this->carts()->recompute($cart);
        $this->assertSame(220, $cart->calcPriceFinal());
    }

    public function testFreeAmountAndPriceResolver(): void
    {
        $donation = $this->product(0, 0, 'donation', 'donation')->setFreeAmount(true);
        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $donation, amount: 4200, comment: 'Keep going');
        $this->assertSame(4200, $cart->calcPriceFinal());

        $this->expectException(\InvalidArgumentException::class);
        $this->carts()->addProduct($cart, $this->product(slug: 'fixed'), amount: 1);
    }

    public function testMemberPrice(): void
    {
        $product = $this->product(10000, 0);
        $memberCart = $this->carts()->create('member-1');
        $this->carts()->addProduct($memberCart, $product);

        $this->assertSame(5000, $memberCart->calcPriceFinal());
    }

    public function testLimitProductItems(): void
    {
        $membership = $this->product(3000, 0, 'membership', 'membership')->setMaxPerCart(1);
        $cart = $this->carts()->create();

        $this->carts()->addProduct($cart, $membership);
        $kept = $this->carts()->addProduct($cart, $membership, quantity: 1);

        $this->assertCount(1, $cart->getItems());
        $this->assertSame($kept, $cart->getItems()->first());
        $this->assertSame(3000, $cart->calcPriceFinal());
    }

    public function testSameChoiceAddsToItsLine(): void
    {
        $shirt = $this->product(2000, 0, 'shirt', 'shirt')->setMaxPerCart(3);
        $cart = $this->carts()->create();

        $line = $this->carts()->addProduct($cart, $shirt, quantity: 2);
        $this->assertSame($line, $this->carts()->addProduct($cart, $shirt, quantity: 2));

        $this->assertCount(1, $cart->getItems());
        $this->assertSame(3, $line->getQuantity());
        $this->assertSame(6000, $cart->calcPriceFinal());
    }
}
