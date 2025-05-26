<?php

namespace App\Controller;

use App\Service\PaymentService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use OpenApi\Attributes as OA;

#[Route('/api/v1/deposit')]
class PaymentController extends AbstractController
{
    public function __construct(private PaymentService $paymentService)
    {
    }

    #[Route('', name: 'deposit', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/deposit',
        summary: 'Пополнение баланса пользователя',
        description: 'Пополняет баланс текущего пользователя на указанную сумму. Требует аутентификации.',
        tags: ['Платежи'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Баланс успешно пополнен',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'balance', type: 'number', format: 'float'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Некорректная сумма',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
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
    public function deposit(Request $request, #[CurrentUser] ?User $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Требуется аутентификация'], 401);
        }
        $data = json_decode($request->getContent(), true);
        $amount = $data['amount'] ?? null;
        if (!is_numeric($amount) || $amount <= 0) {
            return $this->json(['code' => 400, 'message' => 'Некорректная сумма'], 400);
        }
        try {
            $this->paymentService->deposit($user, (float)$amount);
        } catch (\Throwable $e) {
            return $this->json(['code' => 500, 'message' => 'Ошибка пополнения: ' . $e->getMessage()], 500);
        }
        return $this->json([
            'success' => true,
            'balance' => $user->getBalance(),
        ]);
    }
}
