<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use OpenApi\Attributes as OA;

#[Route('/api/v1/transactions')]
#[OA\Tag(name: 'Транзакции')]
class TransactionController extends AbstractController
{
    public function __construct(
        private readonly TransactionService $transactionService
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'История транзакций', description: 'Возвращает историю начислений и списаний пользователя')]
    #[OA\Parameter(name: 'filter[type]', in: 'query', description: 'Тип: payment или deposit', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filter[course_code]', in: 'query', description: 'Код курса', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filter[skip_expired]', in: 'query', description: 'Пропускать истекшие', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Успешно')]
    #[OA\Response(response: 401, description: 'Требуется аутентификация')]
    public function list(Request $request, #[CurrentUser] ?User $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Требуется аутентификация'], 401);
        }

        $filters = $request->query->all('filter');
        $transactions = $this->transactionService->getUserTransactions($user, $filters);

        return $this->json($transactions);
    }
}
