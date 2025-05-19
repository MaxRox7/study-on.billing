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

#[Route('/api/v1/courses')]
class CourseController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'courses_list', methods: ['GET'])]
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
    public function pay(string $code, #[CurrentUser] ?User $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['code' => 401, 'message' => 'Требуется аутентификация'], 401);
        }
        // Здесь должна быть логика оплаты (проверка баланса, создание транзакции и т.д.)
        // Пока что возвращаем заглушку успешного ответа
        return $this->json([
            'success' => true,
            'course_type' => 'rent',
            'expires_at' => (new \DateTimeImmutable('+30 days'))->format(DATE_ATOM),
        ]);
    }
}
