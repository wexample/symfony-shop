<?php

namespace Wexample\SymfonyShop\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyShop\Interface\CartItemPaidHandlerInterface;
use Wexample\SymfonyShop\Interface\PriceResolverInterface;

class WexampleSymfonyShopExtension extends AbstractWexampleSymfonyExtension
{
    public const string TAG_PAID_HANDLER = 'wexample_symfony_shop.paid_handler';

    public const string TAG_PRICE_RESOLVER = 'wexample_symfony_shop.price_resolver';

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $container
            ->registerForAutoconfiguration(CartItemPaidHandlerInterface::class)
            ->addTag(self::TAG_PAID_HANDLER);

        $container
            ->registerForAutoconfiguration(PriceResolverInterface::class)
            ->addTag(self::TAG_PRICE_RESOLVER);

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
