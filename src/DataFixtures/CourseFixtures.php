<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public const COURSE_MATH_REFERENCE = 'course-math';
    public const COURSE_PHYSICS_REFERENCE = 'course-physics';
    public const COURSE_CHEMISTRY_REFERENCE = 'course-chemistry';
    public const COURSE_BIOLOGY_REFERENCE = 'course-biology';
    public const COURSE_CS_REFERENCE = 'course-cs';
    public const COURSE_FREE_REFERENCE = 'course-free';
    public const COURSE_EXPENSIVE_REFERENCE = 'course-expensive';

    public function load(ObjectManager $manager): void
    {
        $courses = [
            [
                'code' => 'MATH101', 
                'type' => 0, // rent
                'price' => 100.0,
                'reference' => self::COURSE_MATH_REFERENCE
            ],
            [
                'code' => 'PHYS202', 
                'type' => 1, // buy
                'price' => 250.0,
                'reference' => self::COURSE_PHYSICS_REFERENCE
            ],
            [
                'code' => 'CHEM303', 
                'type' => 0, // rent
                'price' => 150.5,
                'reference' => self::COURSE_CHEMISTRY_REFERENCE
            ],
            [
                'code' => 'BIO404',  
                'type' => 1, // buy
                'price' => 300.0,
                'reference' => self::COURSE_BIOLOGY_REFERENCE
            ],
            [
                'code' => 'CS505',   
                'type' => 0, // rent
                'price' => 200.0,
                'reference' => self::COURSE_CS_REFERENCE
            ],
            [
                'code' => 'FREE101',   
                'type' => 3, // free
                'price' => 0.0,
                'reference' => self::COURSE_FREE_REFERENCE
            ],
            [
                'code' => 'EXPENSIVE999',   
                'type' => 1, // buy
                'price' => 5000.0,
                'reference' => self::COURSE_EXPENSIVE_REFERENCE
            ],
        ];

        foreach ($courses as $data) {
            $course = new Course();
            $course->setCode($data['code']);
            $course->setType($data['type']);
            $course->setPrice($data['price']);
            
            $manager->persist($course);
            $this->addReference($data['reference'], $course);
        }

        $manager->flush();
    }
}
