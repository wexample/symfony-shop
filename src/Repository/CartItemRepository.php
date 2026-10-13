<?php

namespace Wexample\SymfonyShop\Repository;

use DateTimeImmutable;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyShop\Entity\CartItem;
use Wexample\SymfonyShop\Entity\Product;
use Wexample\SymfonyShop\Enum\CartStatus;

class CartItemRepository extends AbstractRepository
{
    public static function getEntityClassName(): string
    {
        return CartItem::class;
    }

    /**
     * Paid sales of a product: units and amount, optionally over a period.
     *
     * @return array{quantity: int, amount: int, count: int}
     */
    public function sumPaidSales(
        Product $product,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null
    ): array {
        $builder = $this->createQueryBuilder('i')
            ->select('COALESCE(SUM(i.quantity), 0) AS quantity, COALESCE(SUM(i.priceTotal), 0) AS amount, COUNT(i.id) AS count')
            ->join('i.cart', 'c')
            ->where('i.product = :product')
            ->andWhere('c.status IN (:paid)')
            ->setParameter('product', $product->getId(), UuidType::NAME)
            ->setParameter('paid', [CartStatus::Paid->value, CartStatus::Fulfilled->value]);

        if ($from) {
            $builder->andWhere('c.datePaid >= :from')->setParameter('from', $from);
        }

        if ($to) {
            $builder->andWhere('c.datePaid <= :to')->setParameter('to', $to);
        }

        $row = $builder->getQuery()->getSingleResult();

        return array_map('intval', $row);
    }
}
