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

        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('wexample_symfony_shop.digital.download_lifetime_days', $config['digital']['download_lifetime_days']);
        $container->setParameter('wexample_symfony_shop.digital.download_max_uses', $config['digital']['download_max_uses']);

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
