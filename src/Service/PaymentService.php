<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Course;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PaymentService
{
    public const TYPE_DEPOSIT = 0;
    public const TYPE_PAYMENT = 1;
    public const COURSE_TYPE_RENT = 0;
    public const COURSE_TYPE_FREE = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private TransactionRepository $transactionRepository,
        private CourseRepository $courseRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Пополнение счета пользователя
     * @throws \InvalidArgumentException|\Throwable
     */
    public function deposit(User $user, float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма пополнения должна быть положительной');
        }
        $this->em->wrapInTransaction(function () use ($user, $amount) {
            $transaction = $this->createTransaction(
                user: $user,
                course: null,
                type: self::TYPE_DEPOSIT,
                amount: $amount,
                expiresAt: null
            );
            $user->setBalance($user->getBalance() + $amount);
            $this->em->persist($user);
        });
    }

    /**
     * Оплата курса пользователем
     * @throws \RuntimeException
     */
    public function payCourse(User $user, Course $course): Transaction
    {
        return $this->em->wrapInTransaction(function () use ($user, $course) {
            $price = $course->getPrice();
            if ($course->getType() === self::COURSE_TYPE_FREE) {
                return $this->createTransaction(
                    user: $user,
                    course: $course,
                    type: self::TYPE_PAYMENT,
                    amount: 0,
                    expiresAt: null
                );
            }
            if ($user->getBalance() < $price) {
                throw new \RuntimeException('Недостаточно средств на балансе');
            }
            $expiresAt = null;
            if ($course->getType() === self::COURSE_TYPE_RENT) {
                $expiresAt = (new \DateTimeImmutable())->modify('+30 days');
            }
            $transaction = $this->createTransaction(
                user: $user,
                course: $course,
                type: self::TYPE_PAYMENT,
                amount: -$price,
                expiresAt: $expiresAt
            );
            $user->setBalance($user->getBalance() - $price);
            $this->em->persist($user);
            return $transaction;
        });
    }

    /**
     * Создание и сохранение транзакции
     */
    private function createTransaction(User $user, ?Course $course, int $type, float $amount, ?\DateTimeImmutable $expiresAt): Transaction
    {
        $transaction = new Transaction();
        $transaction->setUser($user);
        if ($course) {
            $transaction->setCourse($course);
        }
        $transaction->setType($type);
        $transaction->setAmount($amount);
        $transaction->setCreatedAt(new \DateTimeImmutable());
        $transaction->setExpiresAt($expiresAt);
        $this->em->persist($transaction);
        $this->em->flush();
        return $transaction;
    }
}
