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
#[OA\Tag(name: 'Платежи')]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Пополнение баланса', description: 'Пополняет баланс пользователя на указанную сумму')]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [
        new OA\Property(property: 'amount', type: 'number', description: 'Сумма пополнения')
    ]))]
    #[OA\Response(response: 200, description: 'Баланс пополнен')]
    #[OA\Response(response: 400, description: 'Некорректная сумма')]
    #[OA\Response(response: 401, description: 'Требуется аутентификация')]
    public function deposit(Request $request, #[CurrentUser] ?User $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Требуется аутентификация'], 401);
        }

        $data = $this->getJsonData($request);
        $amount = $data['amount'] ?? null;

        if (!$this->isValidAmount($amount)) {
            return $this->json(['code' => 400, 'message' => 'Некорректная сумма'], 400);
        }

        try {
            $this->paymentService->deposit($user, (float) $amount);
            return $this->json([
                'success' => true,
                'balance' => $user->getBalance(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'code' => 500, 
                'message' => 'Ошибка пополнения: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getJsonData(Request $request): array
    {
        return json_decode($request->getContent(), true) ?? [];
    }

    private function isValidAmount($amount): bool
    {
        return is_numeric($amount) && $amount > 0;
    }
}
