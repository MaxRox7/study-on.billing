<?php

namespace App\DataFixtures;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TransactionFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Получаем пользователей и курсы (предполагается, что они уже есть в базе)
        $users = $manager->getRepository(User::class)->findAll();
        $courses = $manager->getRepository(Course::class)->findAll();
        if (count($users) < 2 || count($courses) < 2) {
            throw new \RuntimeException('Для фикстур транзакций требуется минимум 2 пользователя и 2 курса.');
        }
        $now = new \DateTimeImmutable();
        $transactions = [
            // Начисление без курса (например, пополнение баланса)
            ['user' => $users[0], 'course' => null, 'type' => 0, 'amount' => 500.0, 'createdAt' => $now->modify('-5 days'), 'expiresAt' => null],
            // Списание за арендуемый курс
            ['user' => $users[0], 'course' => $courses[0], 'type' => 1, 'amount' => -100.0, 'createdAt' => $now->modify('-4 days'), 'expiresAt' => $now->modify('+26 days')],
            // Списание за полный курс
            ['user' => $users[1], 'course' => $courses[1], 'type' => 1, 'amount' => -250.0, 'createdAt' => $now->modify('-3 days'), 'expiresAt' => null],
            // Начисление (например, возврат)
            ['user' => $users[1], 'course' => null, 'type' => 0, 'amount' => 150.0, 'createdAt' => $now->modify('-2 days'), 'expiresAt' => null],
            // Списание за арендуемый курс с истекающим сроком
            ['user' => $users[0], 'course' => $courses[2], 'type' => 1, 'amount' => -150.5, 'createdAt' => $now->modify('-1 day'), 'expiresAt' => $now->modify('+29 days')],
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
}
