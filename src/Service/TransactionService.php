<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class TransactionService
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}


    public function getUserTransactions(User $user, array $filters = []): array
    {
        $qb = $this->createBaseQuery($user);
        $this->applyFilters($qb, $filters);
        $qb->orderBy('t.createdAt', 'DESC');

        $transactions = $qb->getQuery()->getResult();

        return array_map([$this, 'formatTransaction'], $transactions);
    }

    private function createBaseQuery(User $user): QueryBuilder
    {
        return $this->em->getRepository(Transaction::class)
            ->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user);
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['type'])) {
            $this->applyTypeFilter($qb, $filters['type']);
        }

        if (isset($filters['course_code'])) {
            $this->applyCourseCodeFilter($qb, $filters['course_code']);
        }

        if (!empty($filters['skip_expired'])) {
            $this->applySkipExpiredFilter($qb);
        }
    }

    private function applyTypeFilter(QueryBuilder $qb, string $type): void
    {
        match ($type) {
            'payment' => $qb->andWhere('t.type = 1'),
            'deposit' => $qb->andWhere('t.type = 0'),
            default => null
        };
    }

    private function applyCourseCodeFilter(QueryBuilder $qb, string $courseCode): void
    {
        $qb->join('t.course', 'c')
           ->andWhere('c.code = :course_code')
           ->setParameter('course_code', $courseCode);
    }

    private function applySkipExpiredFilter(QueryBuilder $qb): void
    {
        $qb->andWhere('(t.expiresAt IS NULL OR t.expiresAt > :now)')
           ->setParameter('now', new \DateTimeImmutable());
    }

    private function formatTransaction(Transaction $transaction): array
    {
        $data = [
            'id' => $transaction->getId(),
            'created_at' => $transaction->getCreatedAt()->format(DATE_ATOM),
            'type' => $transaction->getType() === 1 ? 'payment' : 'deposit',
            'amount' => number_format($transaction->getAmount(), 2, '.', ''),
        ];

        if ($transaction->getCourse()) {
            $data['course_code'] = $transaction->getCourse()->getCode();
        }

        if ($transaction->getExpiresAt()) {
            $data['expires_at'] = $transaction->getExpiresAt()->format(DATE_ATOM);
        }

        return $data;
    }
} 