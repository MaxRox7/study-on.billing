<?php

namespace App\DataFixtures;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TransactionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Получаем пользователей из ссылок
        /** @var User $user */
        $user = $this->getReference(UserFixtures::USER_REFERENCE);
        /** @var User $richUser */
        $richUser = $this->getReference(UserFixtures::RICH_USER_REFERENCE);
        /** @var User $testUser */
        $testUser = $this->getReference(UserFixtures::TEST_USER_REFERENCE);

        // Получаем курсы из ссылок
        /** @var Course $mathCourse */
        $mathCourse = $this->getReference(CourseFixtures::COURSE_MATH_REFERENCE);
        /** @var Course $physicsCourse */
        $physicsCourse = $this->getReference(CourseFixtures::COURSE_PHYSICS_REFERENCE);
        /** @var Course $freeCourse */
        $freeCourse = $this->getReference(CourseFixtures::COURSE_FREE_REFERENCE);

        $transactions = [
            // Пополнения баланса
            [
                'user' => $user,
                'course' => null,
                'type' => 0, // deposit
                'amount' => 100.0,
                'createdAt' => new \DateTimeImmutable('-30 days'),
                'expiresAt' => null,
            ],
            [
                'user' => $richUser,
                'course' => null,
                'type' => 0, // deposit
                'amount' => 10000.0,
                'createdAt' => new \DateTimeImmutable('-25 days'),
                'expiresAt' => null,
            ],
            // Покупка курса навсегда
            [
                'user' => $richUser,
                'course' => $physicsCourse,
                'type' => 1, // payment
                'amount' => -250.0,
                'createdAt' => new \DateTimeImmutable('-20 days'),
                'expiresAt' => null, // навсегда
            ],
            // Аренда курса (активная)
            [
                'user' => $user,
                'course' => $mathCourse,
                'type' => 1, // payment
                'amount' => -100.0,
                'createdAt' => new \DateTimeImmutable('-5 days'),
                'expiresAt' => new \DateTimeImmutable('+25 days'), // активная аренда
            ],
            // Аренда курса (истекшая)
            [
                'user' => $testUser,
                'course' => $mathCourse,
                'type' => 1, // payment
                'amount' => -100.0,
                'createdAt' => new \DateTimeImmutable('-50 days'),
                'expiresAt' => new \DateTimeImmutable('-20 days'), // истекшая аренда
            ],
            // Бесплатный курс
            [
                'user' => $user,
                'course' => $freeCourse,
                'type' => 1, // payment
                'amount' => 0.0,
                'createdAt' => new \DateTimeImmutable('-10 days'),
                'expiresAt' => null,
            ],
        ];

        foreach ($transactions as $data) {
            $transaction = new Transaction();
            $transaction->setUser($data['user']);
            $transaction->setCourse($data['course']);
            $transaction->setType($data['type']);
            $transaction->setAmount($data['amount']);
            $transaction->setCreatedAt($data['createdAt']);
            $transaction->setExpiresAt($data['expiresAt']);

            $manager->persist($transaction);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CourseFixtures::class,
        ];
    }
}
