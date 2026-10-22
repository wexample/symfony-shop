<?php

namespace Wexample\SymfonyShop\Service\Delivery;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyMedia\Entity\MediaAccessGrant;
use Wexample\SymfonyMedia\Repository\MediaAccessGrantRepository;
use Wexample\SymfonyMedia\Service\MediaAccessService;
use Wexample\SymfonyShop\Entity\CartItem;
use Wexample\SymfonyShop\Entity\Product;
use Wexample\SymfonyShop\Event\DigitalGoodsDeliveredEvent;
use Wexample\SymfonyShop\Interface\CartItemPaidHandlerInterface;

/**
 * A paid digital product: one grant per file, as long and as many times as
 * the product says — its metadata, else the configuration —, then
 * DigitalGoodsDeliveredEvent for whoever writes to the buyer.
 */
class DigitalDeliveryHandler implements CartItemPaidHandlerInterface
{
    public const string ORIGIN_PREFIX = 'shop_cart_item:';

    public function __construct(
        private readonly MediaAccessService $accessService,
        private readonly MediaAccessGrantRepository $grantRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(param: 'wexample_symfony_shop.digital.download_lifetime_days')]
        private readonly int $lifetimeDays,
        #[Autowire(param: 'wexample_symfony_shop.digital.download_max_uses')]
        private readonly int $maxUses,
    ) {
    }

    public function supports(string $productType): bool
    {
        return Product::TYPE_DIGITAL === $productType;
    }

    public function handle(CartItem $item): void
    {
        $product = $item->getProduct();
        $metadata = $product->getMetadata();
        $lifetimeDays = (int) ($metadata['download_lifetime_days'] ?? $this->lifetimeDays);
        $maxUses = (int) ($metadata['download_max_uses'] ?? $this->maxUses);
        $grants = [];

        foreach ($product->getFiles() as $media) {
            $grants[] = $this->accessService->grant(
                $media,
                $lifetimeDays > 0 ? new DateTimeImmutable('+'.$lifetimeDays.' days') : null,
                $maxUses > 0 ? $maxUses : null,
                self::ORIGIN_PREFIX.$item->getId(),
            );
        }

        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new DigitalGoodsDeliveredEvent($item, $grants));
    }

    /**
     * The grants a paid item gave, for an order page to list again.
     *
     * @return list<MediaAccessGrant>
     */
    public function getGrants(CartItem $item): array
    {
        return $this->grantRepository->findByOrigin(self::ORIGIN_PREFIX.$item->getId());
    }
}
