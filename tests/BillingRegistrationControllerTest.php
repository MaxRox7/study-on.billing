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

class BillingRegistrationControllerTest extends WebTestCase
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
        $this->em->getConnection()->beginTransaction();
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
        $this->assertArrayHasKey('email', $response['errors'], 'Ошибка должна быть именно для поля email');
        $this->assertEquals('Неверный формат email', $response['errors']['email'], 'Ошибка должна точно соответствовать ожидаемому тексту');
        
        // Проверяем, что ошибка только для поля email, а не для password
        $this->assertArrayNotHasKey('password', $response['errors'], 'Для корректного password не должно быть ошибки');
    }

    public function testRegistrationWithExistingEmail(): void
    {
        // Загружаем фикстуры, чтобы использовать существующего пользователя
        $this->loadFixtures();

        $this->client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'user@mail.ru', // Используем email из фикстур
                'password' => 'newpassword'
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('email', $response['errors'], 'Ошибка должна быть именно для поля email');
        $this->assertEquals('Пользователь с таким email уже существует', $response['errors']['email'], 'Ошибка должна точно соответствовать ожидаемому тексту');
        
        // Проверяем, что ошибка только для поля email, а не для password
        $this->assertArrayNotHasKey('password', $response['errors'], 'Для корректного password не должно быть ошибки');
    }
    

    public function testGetCurrentUserAuthenticated(): void
    {
        // Загружаем фикстуры и используем готового пользователя
        $this->loadFixtures();
        
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($user, 'Пользователь из фикстур должен существовать');

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
        $this->assertArrayHasKey('password', $response['errors'], 'Ошибка должна быть именно для поля password');
        $this->assertStringContainsString('минимум 6 символов', $response['errors']['password'], 'Ошибка должна содержать информацию о минимальной длине пароля');
        
        // Проверяем, что ошибка только для поля password, а не для email
        $this->assertArrayNotHasKey('email', $response['errors'], 'Для корректного email не должно быть ошибки');
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
        $this->assertArrayHasKey('password', $response['errors'], 'Ошибка должна быть именно для поля password');
        $this->assertEquals('Пароль обязателен', $response['errors']['password'], 'Ошибка должна точно соответствовать ожидаемому тексту');
        
        // Проверяем, что ошибка только для поля password, а не для email
        $this->assertArrayNotHasKey('email', $response['errors'], 'Для корректного email не должно быть ошибки');
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
        $this->assertArrayHasKey('email', $response['errors'], 'Ошибка должна быть именно для поля email');
        $this->assertEquals('Email обязателен', $response['errors']['email'], 'Ошибка должна точно соответствовать ожидаемому тексту');
        
        // Проверяем, что ошибка только для поля email, а не для password
        $this->assertArrayNotHasKey('password', $response['errors'], 'Для корректного password не должно быть ошибки');
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
