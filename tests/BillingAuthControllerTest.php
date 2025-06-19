<?php

namespace App\Tests;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
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
        
        // Загружаем фикстуры
        $this->loadFixtures();
    }

    private function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new UserFixtures($this->passwordHasher));
        
        $purger = new ORMPurger($this->em);
        $executor = new ORMExecutor($this->em, $purger);
        $executor->execute($loader->getFixtures());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testSuccessfulAuth(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'user@mail.ru',
                'password' => 'password'
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
                'password' => 'password'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        // Проверяем что это ошибка авторизации
        $this->assertTrue(isset($response['code']) || isset($response['message']) || isset($response['error']));
    }

    public function testAuthWithInvalidPassword(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'user@mail.ru',
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
        $this->assertArrayHasKey('email', $response['error'], 'Ошибка должна быть именно для поля email');
        $this->assertEquals('Email обязателен', $response['error']['email'], 'Ошибка должна точно соответствовать ожидаемому тексту');
        
        // Проверяем, что ошибка только для поля email, а не для password
        $this->assertArrayNotHasKey('password', $response['error'], 'Для корректного password не должно быть ошибки');
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
                'email' => 'user@mail.ru'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('password', $response['error'], 'Ошибка должна быть именно для поля password');
        $this->assertEquals('Пароль обязателен', $response['error']['password'], 'Ошибка должна точно соответствовать ожидаемому тексту');
        
        // Проверяем, что ошибка только для поля password, а не для email
        $this->assertArrayNotHasKey('email', $response['error'], 'Для корректного email не должно быть ошибки');
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
        $this->assertArrayHasKey('email', $response['error'], 'Должна быть ошибка для поля email');
        $this->assertArrayHasKey('password', $response['error'], 'Должна быть ошибка для поля password');
        $this->assertEquals('Email обязателен', $response['error']['email'], 'Ошибка email должна точно соответствовать ожидаемому тексту');
        $this->assertEquals('Пароль обязателен', $response['error']['password'], 'Ошибка password должна точно соответствовать ожидаемому тексту');
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
        $this->assertEquals('Неверный формат JSON', $response['error'], 'Ошибка должна точно соответствовать ожидаемому тексту для невалидного JSON');
        
        // Убеждаемся, что это общая ошибка, а не ошибка конкретного поля
        $this->assertIsString($response['error'], 'Ошибка JSON должна быть строкой, а не массивом полей');
    }

    public function testAuthWithValidTokenGeneration(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'password'
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
        $this->assertEquals('test@example.com', $userResponse['email']);
    }

    /**
     * Тест для случаев, когда нужно создать специфичного пользователя прямо в тесте
     */
    public function testAuthWithSpecificUser(): void
    {
        // Создаем специфичного пользователя только для этого теста
        $user = new User();
        $user->setEmail('specific@test.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'special123'));
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
                'email' => 'specific@test.com',
                'password' => 'special123'
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $response);
    }
} 