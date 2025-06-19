<?php

namespace App\DataFixtures;

use App\Entity\Transaction;
use App\Entity\Course;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TransactionFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();
        
        // 1. Транзакции для курса с истекшей арендой
        $expiredRentCourse = $this->getReference(CourseFixtures::COURSE_RENT_EXPIRED_REFERENCE, Course::class);
        $user1 = $this->getReference(UserFixtures::USER_REFERENCE, User::class);
        
        // Аренда, которая истекла неделю назад
        $expiredTransaction = new Transaction();
        $expiredTransaction->setUser($user1);
        $expiredTransaction->setCourse($expiredRentCourse);
        $expiredTransaction->setType(1); // payment type
        $expiredTransaction->setAmount(-$expiredRentCourse->getPrice());
        $expiredTransaction->setCreatedAt($now->modify('-14 days'));
        $expiredTransaction->setExpiresAt($now->modify('-7 days'));
        $manager->persist($expiredTransaction);
        
        // 2. Транзакции для популярного курса (множественные покупки/аренды)
        $popularCourse = $this->getReference(CourseFixtures::COURSE_POPULAR_REFERENCE, Course::class);
        $users = [
            $this->getReference(UserFixtures::USER_REFERENCE, User::class),
            $this->getReference(UserFixtures::TEST_USER_REFERENCE, User::class),
            $this->getReference(UserFixtures::RICH_USER_REFERENCE, User::class),
        ];
        
        foreach ($users as $user) {
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setCourse($popularCourse);
            $transaction->setType(1); // payment type
            $transaction->setAmount(-$popularCourse->getPrice());
            $transaction->setCreatedAt($now->modify('-' . rand(1, 30) . ' days'));
            $transaction->setExpiresAt($now->modify('+' . rand(1, 7) . ' days')); // Активные аренды
            $manager->persist($transaction);
        }
        
        // 3. Транзакции для купленного курса
        $buyCourse = $this->getReference(CourseFixtures::COURSE_BUY_REFERENCE, Course::class);
        $richUser = $this->getReference(UserFixtures::RICH_USER_REFERENCE, User::class);
        
        $buyTransaction = new Transaction();
        $buyTransaction->setUser($richUser);
        $buyTransaction->setCourse($buyCourse);
        $buyTransaction->setType(1); // payment type
        $buyTransaction->setAmount(-$buyCourse->getPrice());
        $buyTransaction->setCreatedAt($now->modify('-5 days'));
        $buyTransaction->setExpiresAt(null); // Покупка без срока истечения
        $manager->persist($buyTransaction);
        
        // 4. Транзакция аренды, которая истекает завтра (для тестирования уведомлений)
        $rentCourse = $this->getReference(CourseFixtures::COURSE_RENT_REFERENCE, Course::class);
        $testUser = $this->getReference(UserFixtures::TEST_USER_REFERENCE, User::class);
        
        $expiringTomorrow = new Transaction();
        $expiringTomorrow->setUser($testUser);
        $expiringTomorrow->setCourse($rentCourse);
        $expiringTomorrow->setType(1); // payment type
        $expiringTomorrow->setAmount(-$rentCourse->getPrice());
        $expiringTomorrow->setCreatedAt($now->modify('-6 days'));
        $expiringTomorrow->setExpiresAt($now->modify('+1 day')); // Истекает завтра
        $manager->persist($expiringTomorrow);
        
        // 5. Депозитные транзакции (пополнение баланса)
        $depositTransaction = new Transaction();
        $depositTransaction->setUser($user1);
        $depositTransaction->setCourse(null); // Депозит не связан с курсом
        $depositTransaction->setType(0); // deposit type
        $depositTransaction->setAmount(500.0);
        $depositTransaction->setCreatedAt($now->modify('-10 days'));
        $depositTransaction->setExpiresAt(null);
        $manager->persist($depositTransaction);
        
        // 6. Бесплатный курс - транзакция с нулевой суммой
        $freeCourse = $this->getReference(CourseFixtures::COURSE_FREE_REFERENCE, Course::class);
        
        foreach ([$user1, $testUser] as $user) {
            $freeTransaction = new Transaction();
            $freeTransaction->setUser($user);
            $freeTransaction->setCourse($freeCourse);
            $freeTransaction->setType(1); // payment type
            $freeTransaction->setAmount(0.0);
            $freeTransaction->setCreatedAt($now->modify('-' . rand(1, 20) . ' days'));
            $freeTransaction->setExpiresAt(null); // Бесплатные курсы без срока
            $manager->persist($freeTransaction);
        }
        
        // 7. Курс без покупок - docker-mastery останется без транзакций
        
        $manager->flush();
    }
} 