<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use App\Entity\RefreshToken;
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
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

#[Route('/api', name: 'api_')]
class RegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenManagerInterface $refreshTokenManager
    ) {
    }

    #[Route('/v1/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/register',
        summary: 'Регистрация нового пользователя',
        description: 'Создаёт нового пользователя и возвращает JWT и Refresh токены.',
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
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'refresh_token', type: 'string')
            ],
            example: [
                'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...',
                'refresh_token' => 'd8a9996c9e9c7645f93fc2d0a90a151e8b9d0e5e2d1f0123'
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Ошибки валидации'
    )]
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

        // JWT токен
        $token = $this->jwtManager->create($user);

        // Refresh токен
        $refreshToken = new RefreshToken();
        $refreshToken->setRefreshToken(bin2hex(random_bytes(64)));
        $refreshToken->setUsername($user->getUserIdentifier());
        $refreshToken->setValid((new \DateTime())->modify('+1 month'));

        $this->refreshTokenManager->save($refreshToken);

        return $this->json(
            [
                'token' => $token,
                'refresh_token' => $refreshToken->getRefreshToken()
            ],
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
        description: 'Возвращает данные текущего авторизованного пользователя.',
        tags: ['Авторизация']
    )]
    #[OA\Response(
        response: 200,
        description: 'Успешно',
    )]
    #[OA\Response(
        response: 401,
        description: 'Неавторизован'
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
