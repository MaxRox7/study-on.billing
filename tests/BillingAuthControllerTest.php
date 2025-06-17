<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BillingAuthControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private JWTManager $jwtManager;
    private $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->jwtManager = self::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        
        // Очищаем таблицу users перед каждым тестом
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testSuccessfulAuth(): void
    {
        // Создаем пользователя для авторизации
        $user = new User();
        $user->setEmail('testuser@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, '123456'));
        $user->setRoles(['ROLE_USER']);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'testuser@example.com',
                'password' => '123456'
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $response);
        $this->assertNotEmpty($response['token']);
    }

    public function testAuthWithInvalidEmail(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nonexistent@example.com',
                'password' => 'password123'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        // Проверяем что это ошибка авторизации
        $this->assertTrue(isset($response['code']) || isset($response['message']) || isset($response['error']));
    }

    public function testAuthWithInvalidPassword(): void
    {
        // Создаем пользователя
        $user = new User();
        $user->setEmail('invalidpassword@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'correctpassword'));
        $user->setRoles(['ROLE_USER']);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'invalidpassword@example.com',
                'password' => 'wrongpassword'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        // Проверяем что это ошибка авторизации
        $this->assertTrue(isset($response['code']) || isset($response['message']) || isset($response['error']));
    }

    public function testAuthWithoutEmail(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'password' => 'password123'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('email', $response['error']);
        $this->assertEquals('Email обязателен', $response['error']['email']);
    }

    public function testAuthWithoutPassword(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nopassword@example.com'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('password', $response['error']);
        $this->assertEquals('Пароль обязателен', $response['error']['password']);
    }

    public function testAuthWithEmptyRequest(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('email', $response['error']);
        $this->assertArrayHasKey('password', $response['error']);
        $this->assertEquals('Email обязателен', $response['error']['email']);
        $this->assertEquals('Пароль обязателен', $response['error']['password']);
    }

    public function testAuthWithInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Неверный формат JSON', $response['error']);
    }

    public function testAuthWithValidTokenGeneration(): void
    {
        // Создаем пользователя
        $user = new User();
        $user->setEmail('tokentest@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER']);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'tokentest@example.com',
                'password' => 'password123'
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        // Проверяем что токен валидный
        $this->assertArrayHasKey('token', $response);
        $token = $response['token'];
        $this->assertNotEmpty($token);
        
        // Используем токен для доступа к защищенному эндпоинту
        $this->client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$token]
        );

        $this->assertResponseIsSuccessful();
        $userResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('tokentest@example.com', $userResponse['email']);
    }
} 