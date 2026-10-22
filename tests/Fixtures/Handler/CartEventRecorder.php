<?php

namespace Wexample\SymfonyShop\Tests\Fixtures\Handler;

class CartEventRecorder
{
    /** @var list<object> */
    public array $events = [];

    public function record(object $event): void
    {
        $this->events[] = $event;
    }

    public function count(string $class): int
    {
        return count(array_filter($this->events, fn (object $event) => $event instanceof $class));
    }
}
