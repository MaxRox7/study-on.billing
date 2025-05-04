<?php

// src/Controller/RegistrationController.php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;

#[Route('/api', name: 'api_')]
class RegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Регистрация нового пользователя',
        description: 'Создаёт нового пользователя с указанным логином, паролем и email-адресом. Требуется валидный формат для пароля и email.',
        tags: ['Авторизация']
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'username', type: 'string', description: 'Username of the user'),
                new OA\Property(property: 'password', type: 'string', description: 'Password for the user'),
                new OA\Property(property: 'email', type: 'string', description: 'User email address'),
            ],
            example: [
                'password' => 'securePassword123',
                'email' => 'johndoe@example.com',
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'User registered successfully',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    description: 'Confirmation message'
                )
            ],
            example: [
                'message' => 'User registered successfully'
            ]
        )
    )]

    #[OA\Response(
        response: 400,
        description: 'Validation failed',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    description: 'Validation errors for specific fields',
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'string'
                    )
                )
            ],
            example: [
                'errors' => [
                    'email' => 'Неверный формат email',
                    'password' => 'Пароль должен содержать минимум 6 символов'
                ]
            ]
        )
    )]
    #[OA\Tag(name: 'Авторизация')]
    #[Security(name: 'Bearer')]


    public function register(Request $request): JsonResponse
    {
        /** @var UserDto $userDto */
        $userDto = $this->serializer->deserialize(
            $request->getContent(),
            UserDto::class,
            'json'
        );

        $errors = $this->validator->validate($userDto);
        if (count($errors) > 0) {
            return $this->formatValidationErrors($errors);
        }

        // Исправлено: userDto->username -> userDto->email
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $userDto->email])) {
            return $this->json(
                ['errors' => ['email' => 'User with this email already exists']],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $user = new User();
        // Исправлено: userDto->username -> userDto->email
        $user->setEmail($userDto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $userDto->password));
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        return $this->json(
            ['message' => 'User registered successfully'],
            JsonResponse::HTTP_CREATED
        );
    }

    private function formatValidationErrors($errors): JsonResponse
    {
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[$error->getPropertyPath()] = $error->getMessage();
        }
        
        return $this->json(
            ['errors' => $errorMessages],
            JsonResponse::HTTP_BAD_REQUEST
        );
    }

    #[Route('/user', name: 'api_user')]
    #[OA\Get(
        path: '/api/user',
        summary: 'Получение текущего пользователя',
        description: 'Возвращает данные текущего авторизованного пользователя. Требуется JWT токен в заголовке Authorization.',
        tags: ['Авторизация']
    )]
    #[OA\Response(
        response: 200,
        description: 'Успешно. Данные пользователя',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'balance', type: 'integer', example: 0),
                new OA\Property(property: 'id', type: 'integer', example: 9),
                new OA\Property(property: 'email', type: 'string', example: 'maxim@bk.ru'),
                new OA\Property(property: 'userIdentifier', type: 'string', example: 'maxim@bk.ru'),
                new OA\Property(
                    property: 'roles',
                    type: 'array',
                    items: new OA\Items(type: 'string', example: 'ROLE_USER')
                ),
                new OA\Property(property: 'password', type: 'string', example: '$2y$13$1h5oiORKDvTPxIFAv4nMnOaJ9kgiVlKs.HPO7sN.0Wyh2Fb8htrfi'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Неавторизован',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'JWT Token not found')
            ]
        )
    )]
    #[Security(name: 'Bearer')]
    public function getCurrentUser(#[CurrentUser] User $user = null): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }
    
        return $this->json($user);
    }
}
