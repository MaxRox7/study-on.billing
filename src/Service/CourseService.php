<?php

namespace App\Service;

use App\Dto\CourseDto;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CourseRepository;

class CourseService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CourseRepository $courseRepository
    ) {}

    /**
     * @return CourseDto[]
     */
    public function getAllCourses(): array
    {
        $courses = $this->em->getRepository(Course::class)->findAll();
        
        return array_map(
            fn(Course $course) => CourseDto::fromEntity($course),
            $courses
        );
    }

    public function getCourseByCode(string $code): ?CourseDto
    {
        $course = $this->em->getRepository(Course::class)->findOneBy(['code' => $code]);
        
        return $course ? CourseDto::fromEntity($course) : null;
    }

    public function createCourse(array $data): Course
    {
        $course = new Course();
        $course->setCode($data['code']);
        $course->setTitle($data['title']);

        $courseType = Course::getTypeFromString($data['type']);
        $price = $courseType !== Course::TYPE_FREE ? (float) $data['price'] : 0.0;

        $course->setPrice($price);
        $course->setType($courseType);

        $this->courseRepository->save($course, true);

        return $course;
    }

    public function updateCourse(Course $course, array $data): Course
    {
        $course->setCode($data['code']);
        $course->setTitle($data['title']);

        $courseType = Course::getTypeFromString($data['type']);
        $price = $courseType !== Course::TYPE_FREE ? (float) $data['price'] : 0.0;

        $course->setPrice($price);
        $course->setType($courseType);

        $this->courseRepository->save($course, true);

        return $course;
    }

    public function validateCourseData(array $data): array
    {
        $errors = [];

        if (empty($data['code'])) {
            $errors['code'] = 'Code is required';
        }

        if (empty($data['title'])) {
            $errors['title'] = 'Title is required';
        }

        $courseType = Course::getTypeFromString($data['type']);
        if (!$courseType) {
            $errors['type'] = 'Invalid course type';
        }

        if ($courseType && $courseType !== Course::TYPE_FREE) {
            if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) {
                $errors['price'] = 'Для платных курсов требуется корректная цена';
            }
        }

        return $errors;
    }

    private function checkCodeUniqueness(string $code): void
    {
        $existingCourse = $this->em->getRepository(Course::class)->findOneBy(['code' => $code]);
        if ($existingCourse) {
            throw new \LogicException('Курс с таким кодом уже существует');
        }
    }

    private function findCourseByCode(string $code): Course
    {
        $course = $this->em->getRepository(Course::class)->findOneBy(['code' => $code]);
        if (!$course) {
            throw new \RuntimeException('Курс не найден');
        }
        
        return $course;
    }
} 