<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'course')]
#[ORM\UniqueConstraint(name: 'UNIQ_COURSE_CODE', columns: ['code'])]
class Course
{
    public const TYPE_RENT = 0;
    public const TYPE_BUY = 1;
    public const TYPE_FREE = 3;

    public const TYPE_STRINGS = [
        self::TYPE_RENT => 'rent',
        self::TYPE_BUY => 'buy',
        self::TYPE_FREE => 'free',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'smallint')]
    private int $type;

    #[ORM\Column(type: 'float')]
    private float $price;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getTypeAsString(): string
    {
        return self::TYPE_STRINGS[$this->type] ?? 'unknown';
    }

    public function requiresPrice(): bool
    {
        return $this->type !== self::TYPE_FREE;
    }

    public static function getTypeFromString(string $type): ?int
    {
        $types = array_flip(self::TYPE_STRINGS);
        return $types[$type] ?? null;
    }
}
