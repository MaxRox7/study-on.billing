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

    #[Route('/user', name: 'api_user', methods: ['GET'])]
    public function getCurrentUser(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(
                ['error' => 'Not authenticated'],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        return $this->json([
            'username' => $user->getEmail(),
            'roles' => $user->getRoles()
        ]);
    }
}
