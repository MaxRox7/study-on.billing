<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Найти транзакции, истекающие в указанный период
     */
    public function findExpiringTransactions(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.user', 'u')
            ->innerJoin('t.course', 'c')
            ->where('t.type = :payment_type')
            ->andWhere('t.expiresAt BETWEEN :start AND :end')
            ->andWhere('t.amount < 0') // только списания (платежи)
            ->setParameter('payment_type', \App\Service\PaymentService::TYPE_PAYMENT)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти транзакции-платежи за указанный период для отчета
     */
    public function findPaymentTransactionsForPeriod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.course', 'c')
            ->where('t.type = :payment_type')
            ->andWhere('t.amount < 0') // только списания (платежи)
            ->andWhere('t.createdAt BETWEEN :start AND :end')
            ->setParameter('payment_type', \App\Service\PaymentService::TYPE_PAYMENT)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
