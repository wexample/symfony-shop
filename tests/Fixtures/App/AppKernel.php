<?php

namespace Wexample\SymfonyShop\Tests\Fixtures\App;

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyGeo\WexampleSymfonyGeoBundle;
use Wexample\SymfonyMedia\WexampleSymfonyMediaBundle;
use Wexample\SymfonyMoney\WexampleSymfonyMoneyBundle;
use Wexample\SymfonyPayment\WexampleSymfonyPaymentBundle;
use Wexample\SymfonyRemotePayment\WexampleSymfonyRemotePaymentBundle;
use Wexample\SymfonyShop\WexampleSymfonyShopBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new WexampleSymfonyMoneyBundle(),
            new WexampleSymfonyGeoBundle(),
            new WexampleSymfonyRemotePaymentBundle(),
            new WexampleSymfonyPaymentBundle(),
            new WexampleSymfonyMediaBundle(),
            new WexampleSymfonyShopBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [
            __DIR__.'/config/config.yaml',
        ];
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // The library's file routes: the links of the files sold point there.
        $routes->import(__DIR__.'/../../../vendor/wexample/symfony-media/src/Controller', 'attribute');
    }
}
