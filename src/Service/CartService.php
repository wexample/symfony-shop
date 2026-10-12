<?php

namespace Wexample\SymfonyShop\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Entity\CartItem;
use Wexample\SymfonyShop\Entity\Product;
use Wexample\SymfonyShop\Entity\ProductVariation;
use Wexample\SymfonyShop\Entity\Schedule;
use Wexample\SymfonyShop\Interface\PriceResolverInterface;
use Wexample\SymfonyShop\Repository\CartRepository;

/**
 * Filling a cart. Every change refuses a cart that is no longer opened.
 */
class CartService
{
    /**
     * @param iterable<PriceResolverInterface> $priceResolvers
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartRepository $cartRepository,
        private readonly iterable $priceResolvers = [],
    ) {
    }

    public function create(
        ?string $ownerReference = null,
        string $currencyCode = 'EUR'
    ): Cart {
        $cart = (new Cart())
            ->setOwnerReference($ownerReference)
            ->setCurrencyCode($currencyCode);

        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $cart;
    }

    /**
     * The owner's opened cart, or a new one.
     */
    public function getOrCreateOpened(string $ownerReference): Cart
    {
        return $this->cartRepository->findOpenedByOwner($ownerReference) ?? $this->create($ownerReference);
    }

    /**
     * Gives an anonymous cart to its buyer once known.
     */
    public function attachOwner(
        Cart $cart,
        string $ownerReference,
        ?string $customerEmail = null
    ): Cart {
        $cart->setOwnerReference($ownerReference);

        if ($customerEmail) {
            $cart->setCustomerEmail($customerEmail);
        }

        $this->entityManager->flush();

        return $cart;
    }

    /**
     * @param ProductVariation[] $variations
     * @param int|null $amount For a free-amount product, the amount chosen, before VAT.
     */
    public function addProduct(
        Cart $cart,
        Product $product,
        int $quantity = 1,
        ?int $amount = null,
        ?Schedule $schedule = null,
        array $variations = [],
        ?string $comment = null,
    ): CartItem {
        $cart->assertEditable();

        if (! $product->isSalable()) {
            throw new \InvalidArgumentException(sprintf('Product "%s" is not for sale.', $product->getSlug()));
        }

        if (null !== $amount && ! $product->isFreeAmount()) {
            throw new \InvalidArgumentException(sprintf('Product "%s" has a fixed price.', $product->getSlug()));
        }

        // The same thing chosen again is one more of it, within the
        // product's limit — not a second line.
        if ($same = $this->findSameItem($cart, $product, $amount, $schedule, $variations, $comment)) {
            $total = $same->getQuantity() + $quantity;
            $same->setQuantity(null === $product->getMaxPerCart() ? $total : min($total, $product->getMaxPerCart()));
            $cart->touch();
            $this->entityManager->flush();

            return $same;
        }

        $item = new CartItem();
        $item->setProduct($product, $amount ?? $this->resolvePrice($product, $cart))
            ->setSchedule($schedule)
            ->setComment($comment);

        foreach ($variations as $variation) {
            if ($variation->getProduct() !== $product) {
                throw new \InvalidArgumentException('A variation of another product cannot be chosen.');
            }

            $item->addVariation($variation);
        }

        $item->setQuantity($quantity);
        $cart->addItem($item);

        if (null !== $product->getMaxPerCart()) {
            $this->limitProductItems($cart, $product, $schedule, $product->getMaxPerCart(), $item);
        }

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    /**
     * @param ProductVariation[] $variations
     */
    private function findSameItem(
        Cart $cart,
        Product $product,
        ?int $amount,
        ?Schedule $schedule,
        array $variations,
        ?string $comment,
    ): ?CartItem {
        // A free amount, a comment: each one is given apart.
        if (null !== $amount || null !== $comment) {
            return null;
        }

        $ids = static fn (iterable $variations): array => array_map(
            static fn (ProductVariation $variation) => (string) $variation->getId(),
            is_array($variations) ? $variations : iterator_to_array($variations)
        );
        $wanted = $ids($variations);
        sort($wanted);

        foreach ($cart->getItems() as $item) {
            $chosen = $ids($item->getVariations());
            sort($chosen);

            if ($item->getProduct() === $product
                && $item->getSchedule() === $schedule
                && null === $item->getComment()
                && $chosen === $wanted) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Keeps at most $max items of the product (for the same schedule, or without one),
     * the most recent first. The kept item is $keep when given.
     */
    public function limitProductItems(
        Cart $cart,
        Product $product,
        ?Schedule $schedule = null,
        int $max = 1,
        ?CartItem $keep = null,
    ): void {
        $cart->assertEditable();
        $matching = [];

        foreach ($cart->getItems() as $item) {
            if ($item->getProduct() === $product && $item->getSchedule() === $schedule) {
                $matching[] = $item;
            }
        }

        // Most recent last; the item to keep goes last whatever its position.
        if ($keep) {
            $matching = array_values(array_filter($matching, fn (CartItem $item) => $item !== $keep));
            $matching[] = $keep;
        }

        foreach (array_slice($matching, 0, max(0, count($matching) - $max)) as $item) {
            $cart->removeItem($item);
        }

        $this->entityManager->flush();
    }

    public function setQuantity(
        CartItem $item,
        int $quantity
    ): void {
        $item->getCart()->assertEditable();

        if ($quantity <= 0) {
            $this->removeItem($item);

            return;
        }

        $item->setQuantity($quantity);
        $this->entityManager->flush();
    }

    public function removeItem(CartItem $item): void
    {
        $item->getCart()->removeItem($item);
        $this->entityManager->flush();
    }

    public function removeProduct(
        Cart $cart,
        Product $product
    ): void {
        foreach ($cart->getItems()->toArray() as $item) {
            if ($item->getProduct() === $product) {
                $cart->removeItem($item);
            }
        }

        $this->entityManager->flush();
    }

    public function clear(Cart $cart): void
    {
        foreach ($cart->getItems()->toArray() as $item) {
            $cart->removeItem($item);
        }

        $this->entityManager->flush();
    }

    /**
     * Refreshes stored totals, e.g. after editing a variation.
     */
    public function recompute(Cart $cart): void
    {
        foreach ($cart->getItems() as $item) {
            $item->applyVariations();
        }

        $cart->updatePriceTotal();
        $this->entityManager->flush();
    }

    private function resolvePrice(
        Product $product,
        Cart $cart
    ): ?int {
        foreach ($this->priceResolvers as $resolver) {
            $price = $resolver->resolvePrice($product, $cart);

            if (null !== $price) {
                return $price;
            }
        }

        return $product->getPriceRaw();
    }
}
