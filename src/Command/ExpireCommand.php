<?php

namespace Wexample\SymfonyShop\Command;

use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wexample\SymfonyShop\Repository\CartRepository;
use Wexample\SymfonyShop\Service\CartCheckoutService;
use Wexample\SymfonyShop\WexampleSymfonyShopBundle;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Service\BundleService;

/**
 * Expires carts left opened or unpaid, and gives their stock back.
 */
class ExpireCommand extends AbstractBundleCommand
{
    public function __construct(
        BundleService $bundleService,
        private readonly CartRepository $cartRepository,
        private readonly CartCheckoutService $checkoutService,
    ) {
        parent::__construct($bundleService);
    }

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyShopBundle::class;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Expires carts not updated for a while.')
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'ISO 8601 duration', 'PT2H');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $before = (new DateTimeImmutable())->sub(new DateInterval($input->getOption('older-than')));
        $count = 0;

        foreach ($this->cartRepository->findStale($before) as $cart) {
            $count += (int) $this->checkoutService->expire($cart);
        }

        $output->writeln(sprintf('%d cart(s) expired.', $count));

        return self::SUCCESS;
    }
}
