<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public const COURSE_FREE_REFERENCE = 'course-free-1';
    public const COURSE_RENT_REFERENCE = 'course-rent-1';
    public const COURSE_BUY_REFERENCE = 'course-buy-1';
    public const COURSE_RENT_EXPIRED_REFERENCE = 'course-rent-expired';
    public const COURSE_NO_PURCHASES_REFERENCE = 'course-no-purchases';
    public const COURSE_POPULAR_REFERENCE = 'course-popular';

    public function load(ObjectManager $manager): void
    {
        $coursesData = [
            [
                'code' => 'html-basics',
                'title' => 'Основы HTML',
                'type' => Course::TYPE_FREE,
                'price' => 0.0,
                'reference' => self::COURSE_FREE_REFERENCE
            ],
            [
                'code' => 'javascript-advanced',
                'title' => 'JavaScript Продвинутый',
                'type' => Course::TYPE_RENT,
                'price' => 99.99,
                'reference' => self::COURSE_RENT_REFERENCE
            ],
            [
                'code' => 'php-professional',
                'title' => 'PHP Профессионал',
                'type' => Course::TYPE_BUY,
                'price' => 299.99,
                'reference' => self::COURSE_BUY_REFERENCE
            ],
            [
                'code' => 'python-basics',
                'title' => 'Python для начинающих',
                'type' => Course::TYPE_RENT,
                'price' => 79.99,
                'reference' => self::COURSE_RENT_EXPIRED_REFERENCE
            ],
            [
                'code' => 'docker-mastery',
                'title' => 'Docker Мастерство',
                'type' => Course::TYPE_BUY,
                'price' => 199.99,
                'reference' => self::COURSE_NO_PURCHASES_REFERENCE
            ],
            [
                'code' => 'react-fundamentals',
                'title' => 'Основы React',
                'type' => Course::TYPE_RENT,
                'price' => 89.99,
                'reference' => self::COURSE_POPULAR_REFERENCE
            ],
        ];

        foreach ($coursesData as $data) {
            $course = new Course();
            $course->setCode($data['code']);
            $course->setTitle($data['title']);
            $course->setType($data['type']);
            $course->setPrice($data['price']);
            
            $manager->persist($course);
            $this->addReference($data['reference'], $course);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class, // Если CourseFixtures использует пользователей
        ];
    }
} 