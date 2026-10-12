<?php

namespace Wexample\SymfonyShop\Repository;

use DateTimeImmutable;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Enum\CartStatus;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;

/**
 * @method Cart|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cart|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cart[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CartRepository extends AbstractRepository
{
    public static function getEntityClassName(): string
    {
        return Cart::class;
    }

    public function findOpenedByOwner(string $ownerReference): ?Cart
    {
        return $this->findOneBy(
            ['ownerReference' => $ownerReference, 'status' => CartStatus::Opened],
            ['dateUpdated' => self::SORT_DESC]
        );
    }

    /**
     * Carts left opened or waiting for payment since before $before.
     *
     * @return Cart[]
     */
    public function findStale(DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->andWhere('c.dateUpdated < :before')
            ->setParameter('statuses', [CartStatus::Opened->value, CartStatus::WaitingPayment->value])
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
}
