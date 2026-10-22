<?php

namespace Wexample\SymfonyShop\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * A time slot a product is sold for (a session, an event date).
 * Only fixed slots exist for now; `rule` is kept for recurrences such as
 * "last Monday of each month" (an RRULE string), not interpreted yet.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cart_schedule')]
class Schedule extends AbstractEntity
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $dateStart;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateEnd = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $label = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $rule = null;

    public function getDateStart(): DateTimeImmutable
    {
        return $this->dateStart;
    }

    public function setDateStart(DateTimeImmutable $dateStart): static
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateEnd(): ?DateTimeImmutable
    {
        return $this->dateEnd;
    }

    public function setDateEnd(?DateTimeImmutable $dateEnd): static
    {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getRule(): ?string
    {
        return $this->rule;
    }

    public function setRule(?string $rule): static
    {
        $this->rule = $rule;

        return $this;
    }

    public function isPast(?DateTimeImmutable $now = null): bool
    {
        return ($this->dateEnd ?? $this->dateStart) < ($now ?? new DateTimeImmutable());
    }
}
