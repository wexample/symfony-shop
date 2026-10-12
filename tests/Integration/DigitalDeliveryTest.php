<?php

namespace Wexample\SymfonyShop\Tests\Integration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Wexample\SymfonyMedia\Controller\MediaFileController;
use Wexample\SymfonyMedia\Service\MediaAccessService;
use Wexample\SymfonyMedia\Service\MediaStorage;
use Wexample\SymfonyShop\Entity\Product;
use Wexample\SymfonyShop\Event\DigitalGoodsDeliveredEvent;
use Wexample\SymfonyShop\Service\Delivery\DigitalDeliveryHandler;

/**
 * A file sold: restricted in the library, one link per file once paid,
 * downloaded as many times as the product allows.
 */
class DigitalDeliveryTest extends AbstractCartTestCase
{
    public function testAPaidDigitalProductDeliversItsFiles(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'book');
        file_put_contents($file, 'the book');
        $book = $this->service(MediaStorage::class)->import($file, 'book.pdf');

        $product = $this->product(1500, 550, Product::TYPE_DIGITAL, 'ebook')
            ->addFile($book)
            ->setMetadata(['download_max_uses' => 1]);
        $this->em()->flush();
        $this->assertTrue($book->isRestricted());

        $delivered = [];
        static::getContainer()->get('event_dispatcher')->addListener(
            DigitalGoodsDeliveredEvent::class,
            function (DigitalGoodsDeliveredEvent $event) use (&$delivered) {
                $delivered = $event->grants;
            }
        );

        $cart = $this->carts()->create('user-1');
        $item = $this->carts()->addProduct($cart, $product);
        $this->checkout()->complete($cart);

        $this->assertCount(1, $delivered);
        $this->assertSame($delivered, $this->service(DigitalDeliveryHandler::class)->getGrants($item));

        $url = $this->service(MediaAccessService::class)->getUrl($delivered[0]);
        $response = $this->service(MediaFileController::class)->grant((string) $delivered[0]->getId(), Request::create($url));
        $this->assertStringStartsWith(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->headers->get('Content-Disposition'));
        $this->assertFalse($delivered[0]->isUsable(), 'The product allowed one download.');
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function service(string $class): object
    {
        return static::getContainer()->get($class);
    }
}
