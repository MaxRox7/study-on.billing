<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;

#[Route('/api', name: 'api_')]
class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register', methods: 'POST')]
    public function index(ManagerRegistry $doctrine, Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        // Получаем EntityManager для работы с базой данных
        $em = $doctrine->getManager();

        // Декодируем JSON из тела запроса
        $decoded = json_decode($request->getContent());
        $email = $decoded->email;
        $plaintextPassword = $decoded->password;

        // Проверяем, что email и пароль не пустые
        if (empty($email) || empty($plaintextPassword)) {
            return $this->json(['message' => 'Email and password are required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Создаём нового пользователя
        $user = new User();
        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $plaintextPassword
        );
        $user->setPassword($hashedPassword);
        $user->setEmail($email);

        // Устанавливаем роль пользователя (по умолчанию ROLE_USER)
        $user->setRoles(['ROLE_USER']);

        // Сохраняем пользователя в базе данных
        $em->persist($user);
        $em->flush();

        // Возвращаем успешный ответ
        return $this->json(['message' => 'User registered successfully'], JsonResponse::HTTP_CREATED);
    }
}
