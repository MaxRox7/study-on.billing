<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $courses = [
            ['code' => 'MATH101', 'type' => 0, 'price' => 100.0],
            ['code' => 'PHYS202', 'type' => 1, 'price' => 250.0],
            ['code' => 'CHEM303', 'type' => 0, 'price' => 150.5],
            ['code' => 'BIO404',  'type' => 1, 'price' => 300.0],
            ['code' => 'CS505',   'type' => 0, 'price' => 200.0],
        ];
        foreach ($courses as $data) {
            $course = new Course();
            $course->setCode($data['code']);
            $course->setType($data['type']);
            $course->setPrice($data['price']);
            $manager->persist($course);
        }
        $manager->flush();
    }
}
