<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BillingRegistrationControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private JWTManager $jwtManager;
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->jwtManager = self::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSuccessfulRegistration(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'newuser@example.com',
                'password' => 'password123'
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'newuser@example.com']);
        $this->assertNotNull($user);
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    public function testRegistrationWithInvalidEmail(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'invalid-email',
                'password' => 'password123'
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertEquals('Неверный формат email', $response['errors']['email']);
    }

    public function testRegistrationWithExistingEmail(): void
    {
        $existingUser = new User();
        $existingUser->setEmail('existing@example.com');
        $existingUser->setPassword($this->passwordHasher->hashPassword($existingUser, 'password'));
        $this->em->persist($existingUser);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'existing@example.com',
                'password' => 'newpassword'
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertEquals('Пользователь с таким email уже существует', $response['errors']['email']);
    }
    

    public function testGetCurrentUserAuthenticated(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER']);
        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        $this->client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$token]
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('test@example.com', $response['email']);
        $this->assertEquals(['ROLE_USER'], $response['roles']);
    }

    public function testGetCurrentUserUnauthenticated(): void
    {
        $this->client->request('GET', '/api/v1/users/current');
        $this->assertResponseStatusCodeSame(401);
    }


    public function testRegistrationWithShortPassword(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'shortpass@example.com',
                'password' => '123'
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString('минимум 6 символов', $response['errors']['password']);
    }

    public function testRegistrationWithoutPassword(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nopassword@example.com'
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertEquals('Пароль обязателен', $response['errors']['password']);
    }

    public function testRegistrationWithoutEmail(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'password' => 'validpassword'
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertEquals('Email обязателен', $response['errors']['email']);
    }

    public function testRegistrationWithEmptyRequest(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('email', $response['errors']);
        $this->assertArrayHasKey('password', $response['errors']);
    }
}
