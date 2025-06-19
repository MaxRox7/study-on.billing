<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CourseService;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

#[Route('/api/v1/courses')]
#[OA\Tag(name: 'Курсы')]
class CourseController extends AbstractController
{
    public function __construct(
        private readonly CourseService $courseService,
        private readonly PaymentService $paymentService
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Список курсов', description: 'Возвращает список всех курсов')]
    #[OA\Response(response: 200, description: 'Успешно')]
    public function list(): JsonResponse
    {
        $courses = $this->courseService->getAllCourses();
        
        return $this->json(array_map(fn($course) => $course->toArray(), $courses));
    }

    #[Route('/{code}', methods: ['GET'])]
    #[OA\Get(summary: 'Получение курса', description: 'Возвращает данные курса по коду')]
    #[OA\Response(response: 200, description: 'Успешно')]
    #[OA\Response(response: 404, description: 'Курс не найден')]
    public function getCourse(string $code): JsonResponse
    {
        $course = $this->courseService->getCourseByCode($code);
        
        if (!$course) {
            return $this->json(['code' => 404, 'message' => 'Курс не найден'], 404);
        }
        
        return $this->json($course->toArray());
    }

    #[Route('/{code}/pay', methods: ['POST'])]
    #[OA\Post(summary: 'Оплата курса', description: 'Оплата курса с личного счета')]
    #[OA\Response(response: 200, description: 'Успешная оплата')]
    #[OA\Response(response: 401, description: 'Требуется аутентификация')]
    #[OA\Response(response: 406, description: 'Недостаточно средств')]
    public function pay(string $code, #[CurrentUser] ?User $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Требуется аутентификация'], 401);
        }

        try {
            $result = $this->paymentService->payCourseByCode($user, $code);
            return $this->json($result);
        } catch (\RuntimeException $e) {
            return $this->json(['code' => 406, 'message' => $e->getMessage()], 406);
        } catch (\Throwable $e) {
            return $this->json(['code' => 500, 'message' => 'Ошибка оплаты: ' . $e->getMessage()], 500);
        }
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[OA\Post(summary: 'Создание курса', description: 'Создание нового курса (только для админов)')]
    #[OA\Response(response: 201, description: 'Курс создан')]
    #[OA\Response(response: 400, description: 'Ошибка валидации')]
    #[OA\Response(response: 409, description: 'Курс уже существует')]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getJsonData($request);
        if (!$data) {
            return $this->json(['code' => 400, 'message' => 'Некорректные данные JSON'], 400);
        }

        try {
            $this->courseService->createCourse($data);
            return $this->json(['success' => true], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['code' => 400, 'message' => $e->getMessage()], 400);
        } catch (\LogicException $e) {
            return $this->json(['code' => 409, 'message' => $e->getMessage()], 409);
        }
    }

    #[Route('/{code}', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[OA\Post(summary: 'Редактирование курса', description: 'Редактирование курса (только для админов)')]
    #[OA\Response(response: 200, description: 'Курс обновлен')]
    #[OA\Response(response: 400, description: 'Ошибка валидации')]
    #[OA\Response(response: 404, description: 'Курс не найден')]
    #[OA\Response(response: 409, description: 'Курс с новым кодом уже существует')]
    public function edit(string $code, Request $request): JsonResponse
    {
        $data = $this->getJsonData($request);
        if (!$data) {
            return $this->json(['code' => 400, 'message' => 'Некорректные данные JSON'], 400);
        }

        try {
            $this->courseService->updateCourse($code, $data);
            return $this->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['code' => 400, 'message' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return $this->json(['code' => 404, 'message' => $e->getMessage()], 404);
        } catch (\LogicException $e) {
            return $this->json(['code' => 409, 'message' => $e->getMessage()], 409);
        }
    }

    private function getJsonData(Request $request): ?array
    {
        return json_decode($request->getContent(), true);
    }
}
