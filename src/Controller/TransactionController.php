<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use OpenApi\Attributes as OA;

#[Route('/api/v1/transactions')]
class TransactionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'transactions_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/transactions',
        summary: 'История начислений и списаний пользователя',
        description: 'Возвращает историю транзакций текущего пользователя. Требует аутентификации. Поддерживает фильтры по типу, коду курса и skip_expired.',
        tags: ['Транзакции'],
        parameters: [
            new OA\Parameter(
                name: 'filter[type]',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['payment', 'deposit']),
                description: 'Тип транзакции: payment (списание) или deposit (начисление)'
            ),
            new OA\Parameter(
                name: 'filter[course_code]',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string'),
                description: 'Символьный код курса'
            ),
            new OA\Parameter(
                name: 'filter[skip_expired]',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                description: 'Пропускать истекшие аренды (expires_at в прошлом)'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешно',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'type', type: 'string'),
                            new OA\Property(property: 'course_code', type: 'string', nullable: true),
                            new OA\Property(property: 'amount', type: 'string'),
                        ]
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Требуется аутентификация',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function list(Request $request, #[CurrentUser] ?User $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Требуется аутентификация'], 401);
        }
        $qb = $this->em->getRepository(Transaction::class)->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user);

        $filters = $request->query->all('filter');
        if (isset($filters['type'])) {
            if ($filters['type'] === 'payment') {
                $qb->andWhere('t.type = 1');
            } elseif ($filters['type'] === 'deposit') {
                $qb->andWhere('t.type = 0');
            }
        }
        if (isset($filters['course_code'])) {
            $qb->join('t.course', 'c')
               ->andWhere('c.code = :course_code')
               ->setParameter('course_code', $filters['course_code']);
        }
        if (!empty($filters['skip_expired'])) {
            $qb->andWhere('(t.expiresAt IS NULL OR t.expiresAt > :now)')
               ->setParameter('now', new \DateTimeImmutable());
        }
        $qb->orderBy('t.createdAt', 'DESC');
        $transactions = $qb->getQuery()->getResult();

        $result = [];
        foreach ($transactions as $transaction) {
            $item = [
                'id' => $transaction->getId(),
                'created_at' => $transaction->getCreatedAt()->format(DATE_ATOM),
                'type' => $transaction->getType() === 1 ? 'payment' : 'deposit',
                'amount' => number_format($transaction->getAmount(), 2, '.', ''),
            ];
            if ($transaction->getCourse()) {
                $item['course_code'] = $transaction->getCourse()->getCode();
            }
            $result[] = $item;
        }
        return $this->json($result);
    }
}
