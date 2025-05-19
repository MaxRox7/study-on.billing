<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use OpenApi\Attributes as OA;
use App\Service\PaymentService;

#[Route('/api/v1/courses')]
class CourseController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private PaymentService $paymentService)
    {
    }

    #[Route('', name: 'courses_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses',
        summary: 'Список курсов',
        description: 'Возвращает список всех курсов. Не требует аутентификации.',
        tags: ['Курсы'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешно',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'code', type: 'string'),
                            new OA\Property(property: 'type', type: 'string'),
                            new OA\Property(property: 'price', type: 'string', nullable: true),
                        ]
                    )
                )
            )
        ]
    )]
    public function list(): JsonResponse
    {
        $courses = $this->em->getRepository(Course::class)->findAll();
        $result = [];
        foreach ($courses as $course) {
            $item = [
                'code' => $course->getCode(),
                'type' => $course->getType() === 0 ? 'rent' : 'buy',
            ];
            if ($course->getType() !== 1) { // buy
                $item['price'] = number_format($course->getPrice(), 2, '.', '');
            }
            $result[] = $item;
        }
        return $this->json($result);
    }

    #[Route('/{code}', name: 'course_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses/{code}',
        summary: 'Получение отдельного курса',
        description: 'Возвращает данные по курсу по символьному коду. Не требует аутентификации.',
        tags: ['Курсы'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешно',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'string'),
                        new OA\Property(property: 'type', type: 'string'),
                        new OA\Property(property: 'price', type: 'string', nullable: true),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Курс не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function getCourse(string $code): JsonResponse
    {
        $course = $this->em->getRepository(Course::class)->findOneBy(['code' => $code]);
        if (!$course) {
            return $this->json(['code' => 404, 'message' => 'Курс не найден'], 404);
        }
        $result = [
            'code' => $course->getCode(),
            'type' => $course->getType() === 0 ? 'rent' : 'buy',
        ];
        if ($course->getType() !== 1) { // buy
            $result['price'] = number_format($course->getPrice(), 2, '.', '');
        }
        return $this->json($result);
    }

    #[Route('/{code}/pay', name: 'course_pay', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/courses/{code}/pay',
        summary: 'Оплата курса',
        description: 'Оплата курса с личного счета пользователя. Требует аутентификации.',
        tags: ['Курсы'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешная оплата',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'course_type', type: 'string'),
                        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
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
            ),
            new OA\Response(
                response: 406,
                description: 'Недостаточно средств',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function pay(string $code, #[CurrentUser] ?User $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Требуется аутентификация'], 401);
        }
        $course = $this->em->getRepository(Course::class)->findOneBy(['code' => $code]);
        if (!$course) {
            return $this->json(['code' => 404, 'message' => 'Курс не найден'], 404);
        }
        try {
            $transaction = $this->paymentService->payCourse($user, $course);
        } catch (\RuntimeException $e) {
            return $this->json(['code' => 406, 'message' => $e->getMessage()], 406);
        } catch (\Throwable $e) {
            return $this->json(['code' => 500, 'message' => 'Ошибка оплаты: ' . $e->getMessage()], 500);
        }
        $type = $course->getType() === 0 ? 'rent' : 'buy';
        $expiresAt = $transaction->getExpiresAt() ? $transaction->getExpiresAt()->format(DATE_ATOM) : null;
        return $this->json([
            'success' => true,
            'course_type' => $type,
            'expires_at' => $expiresAt,
        ]);
    }
}
