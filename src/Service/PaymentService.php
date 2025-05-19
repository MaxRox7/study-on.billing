<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Course;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception as DBALException;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TransactionRepository $transactionRepository,
        private CourseRepository $courseRepository
    ) {
    }

    /**
     * Пополнение счета пользователя
     * @throws \Throwable
     */
    public function deposit(User $user, float $amount): void
    {
        $this->em->beginTransaction();
        try {
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setType(0); // deposit
            $transaction->setAmount($amount);
            $transaction->setCreatedAt(new \DateTimeImmutable());
            $transaction->setExpiresAt(null);
            $this->em->persist($transaction);

            $user->setBalance($user->getBalance() + $amount);
            $this->em->persist($user);

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    /**
     * Оплата курса пользователем
     * @throws \Exception
     */
    public function payCourse(User $user, Course $course): Transaction
    {
        $this->em->beginTransaction();
        try {
            $price = $course->getPrice();
            if ($user->getBalance() < $price) {
                throw new \RuntimeException('Недостаточно средств на балансе');
            }
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setCourse($course);
            $transaction->setType(1); // payment
            $transaction->setAmount(-$price);
            $transaction->setCreatedAt(new \DateTimeImmutable());
            $expiresAt = null;
            if ($course->getType() === 0) { // rent
                $expiresAt = (new \DateTimeImmutable())->modify('+30 days');
            }
            $transaction->setExpiresAt($expiresAt);
            $this->em->persist($transaction);

            $user->setBalance($user->getBalance() - $price);
            $this->em->persist($user);

            $this->em->flush();
            $this->em->commit();
            return $transaction;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}
