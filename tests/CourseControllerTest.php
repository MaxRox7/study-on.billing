<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\DataFixtures\TransactionFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Course;
use App\Entity\User;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CourseControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private JWTManager $jwtManager;
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = static::getContainer();
        
        $this->em = $container->get(EntityManagerInterface::class);
        $this->jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');
        
        // Начинаем транзакцию для изоляции тестов
        $this->em->getConnection()->beginTransaction();
        
        // Загружаем фикстуры
        $this->loadFixtures();
    }

    private function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new UserFixtures(static::getContainer()->get(UserPasswordHasherInterface::class)));
        $loader->addFixture(new CourseFixtures());
        $loader->addFixture(new TransactionFixtures());
        
        $purger = new ORMPurger($this->em);
        $executor = new ORMExecutor($this->em, $purger);
        $executor->execute($loader->getFixtures());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Проверяем, что транзакция еще активна перед откатом
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        
        // Закрываем entity manager
        $this->em->close();
    }

    private function getAuthHeader(string $email): array
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        $token = $this->jwtManager->create($user);
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
    }

    public function testGetCoursesList(): void
    {
        $this->client->request('GET', '/api/v1/courses');
        
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertIsArray($response);
        $this->assertGreaterThan(0, count($response));
        
        // Проверяем структуру курса
        $course = $response[0];
        $this->assertArrayHasKey('code', $course);
        $this->assertArrayHasKey('title', $course);
        $this->assertArrayHasKey('type', $course);
        $this->assertArrayHasKey('price', $course);
    }

    public function testGetCourseByCode(): void
    {
        $this->client->request('GET', '/api/v1/courses/php-professional');
        
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertEquals('php-professional', $response['code']);
        $this->assertEquals('PHP Профессионал', $response['title']);
        $this->assertEquals('buy', $response['type']);
        $this->assertEquals(299.99, $response['price']);
    }

    public function testGetNonExistentCourse(): void
    {
        $this->client->request('GET', '/api/v1/courses/non-existent-course');
        
        $this->assertResponseStatusCodeSame(404);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
    }

    public function testCreateCourseAsAdmin(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/courses',
            [],
            [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->getAuthHeader('admin@mail.ru')
            ),
            json_encode([
                'code' => 'new-course',
                'title' => 'Новый курс',
                'type' => 'rent',
                'price' => 149.99
            ])
        );
        
        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        
        // Проверяем, что курс создан
        $course = $this->em->getRepository(Course::class)->findOneBy(['code' => 'new-course']);
        $this->assertNotNull($course);
        $this->assertEquals('Новый курс', $course->getTitle());
        $this->assertEquals(Course::TYPE_RENT, $course->getType());
        $this->assertEquals(149.99, $course->getPrice());
    }


    public function testCreateCourseWithoutAuth(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'code' => 'new-course',
                'title' => 'Новый курс',
                'type' => 'rent',
                'price' => 149.99
            ])
        );
        
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateCourseWithDuplicateCode(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/courses',
            [],
            [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->getAuthHeader('admin@mail.ru')
            ),
            json_encode([
                'code' => 'php-professional', // Уже существует
                'title' => 'Дубликат',
                'type' => 'buy',
                'price' => 199.99
            ])
        );
        
        $this->assertResponseStatusCodeSame(409);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
    }

    public function testEditCourseAsAdmin(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/courses/python-basics',
            [],
            [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->getAuthHeader('admin@mail.ru')
            ),
            json_encode([
                'code' => 'python-advanced',
                'title' => 'Python Продвинутый',
                'type' => 'buy',
                'price' => 399.99
            ])
        );
        
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        
        // Проверяем, что курс обновлен
        $course = $this->em->getRepository(Course::class)->findOneBy(['code' => 'python-advanced']);
        $this->assertNotNull($course);
        $this->assertEquals('Python Продвинутый', $course->getTitle());
        $this->assertEquals(Course::TYPE_BUY, $course->getType());
        $this->assertEquals(399.99, $course->getPrice());
        
        // Старый код не должен существовать
        $oldCourse = $this->em->getRepository(Course::class)->findOneBy(['code' => 'python-basics']);
        $this->assertNull($oldCourse);
    }



    public function testEditNonExistentCourse(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/courses/non-existent',
            [],
            [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->getAuthHeader('admin@mail.ru')
            ),
            json_encode([
                'code' => 'new-code',
                'title' => 'Updated',
                'type' => 'rent',
                'price' => 99.99
            ])
        );
        
        $this->assertResponseStatusCodeSame(404);
    }

    public function testEditCourseWithDuplicateCode(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/courses/python-basics',
            [],
            [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->getAuthHeader('admin@mail.ru')
            ),
            json_encode([
                'code' => 'php-professional', // Уже существует
                'title' => 'Конфликт',
                'type' => 'buy',
                'price' => 199.99
            ])
        );
        
        $this->assertResponseStatusCodeSame(409);
    }

    public function testCreateCourseWithInvalidData(): void
    {
        // Без обязательных полей
        $this->client->request(
            'POST',
            '/api/v1/courses',
            [],
            [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->getAuthHeader('admin@mail.ru')
            ),
            json_encode([
                'code' => 'incomplete-course'
                // отсутствуют title, type, price
            ])
        );
        
        $this->assertResponseStatusCodeSame(400);
        
        // С неверным типом
        $this->client->request(
            'POST',
            '/api/v1/courses',
            [],
            [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->getAuthHeader('admin@mail.ru')
            ),
            json_encode([
                'code' => 'wrong-type',
                'title' => 'Неверный тип',
                'type' => 'invalid',
                'price' => 100
            ])
        );
        
        $this->assertResponseStatusCodeSame(400);
    }

    public function testPayForCourse(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/courses/javascript-advanced/pay',
            [],
            [],
            $this->getAuthHeader('rich@example.com')
        );
        
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('course_type', $response);
        $this->assertEquals('rent', $response['course_type']);
        
        // Если аренда, проверяем expires_at
        if ($response['course_type'] === 'rent') {
            $this->assertArrayHasKey('expires_at', $response);
        }
    }

    public function testPayForCourseWithInsufficientBalance(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/courses/php-professional/pay',
            [],
            [],
            $this->getAuthHeader('test@example.com') // У него мало денег
        );
        
        $this->assertResponseStatusCodeSame(406);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
    }
} 