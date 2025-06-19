<?php

namespace App\Dto;

use App\Entity\Course;

class CourseDto
{
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        public readonly string $type,
        public readonly float $price,
    ) {}

    public static function fromEntity(Course $course): self
    {
        return new self(
            code: $course->getCode(),
            title: $course->getTitle(),
            type: $course->getTypeAsString(),
            price: $course->getPrice(),
        );
    }

    public function toArray(): array
    {
        $data = [
            'code' => $this->code,
            'title' => $this->title,
            'type' => $this->type,
        ];

        if ($this->price !== null) {
            $data['price'] = $this->price;
        }

        return $data;
    }
} 