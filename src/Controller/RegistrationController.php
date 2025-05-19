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
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

#[Route('/api', name: 'api_')]
class RegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    #[Route('/v1/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/register',
        summary: 'Регистрация нового пользователя',
        description: 'Создаёт нового пользователя и возвращает JWT токен для авторизации.',
        tags: ['Авторизация']
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', minLength: 6)
            ],
            example: [
                'email' => 'user@example.com',
                'password' => 'securePassword123'
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Успешная регистрация',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string')
            ],
            example: [
                'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Ошибки валидации',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string')
                )
            ],
            example: [
                'errors' => [
                    'email' => 'Неверный формат email',
                    'password' => 'Пароль должен быть не менее 6 символов'
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

        if ($this->em->getRepository(User::class)->findOneBy(['email' => $userDto->email])) {
            return $this->json(
                ['errors' => ['email' => 'Пользователь с таким email уже существует']],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $user = new User();
        $user->setEmail($userDto->email);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $userDto->password)
        );
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        // Генерируем JWT токен
        $token = $this->jwtManager->create($user);

        return $this->json(
            ['token' => $token],
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

    #[Route('/v1/users/current', name: 'api_user', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/users/current',
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
                ),            ]
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
            return $this->json(['message' => 'Not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }
    
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'userIdentifier' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ]);
    }
}
