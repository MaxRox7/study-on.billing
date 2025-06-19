<?php

namespace App\Tests\Command;

use App\Command\PaymentEndingNotificationCommand;
use App\DataFixtures\CourseFixtures;
use App\DataFixtures\TransactionFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\MailerSwitcher;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class PaymentEndingNotificationCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TransactionRepository $transactionRepository;
    private MailerSwitcher $mailerSwitcher;
    private Environment $twig;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = static::getContainer();
        
        $this->em = $container->get(EntityManagerInterface::class);
        $this->transactionRepository = $container->get(TransactionRepository::class);
        $this->mailerSwitcher = $container->get(MailerSwitcher::class);
        $this->twig = $container->get(Environment::class);
        
        // Начинаем транзакцию для изоляции тестов
        $this->em->getConnection()->beginTransaction();
        
        // Загружаем фикстуры
        $this->loadFixtures();
        
        // Настраиваем команду
        $command = new PaymentEndingNotificationCommand(
            $this->transactionRepository,
            $this->mailerSwitcher,
            $this->twig
        );
        
        $application = new Application();
        $application->add($command);
        
        $command = $application->find('payment:ending:notification');
        $this->commandTester = new CommandTester($command);
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

    public function testExecuteWithExpiringCourses(): void
    {
        // Выполняем команду
        $exitCode = $this->commandTester->execute([]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем вывод
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправка уведомлений об окончании срока аренды курсов', $output);
        $this->assertStringContainsString('Уведомление отправлено пользователю', $output);
        $this->assertStringContainsString('Отправлено уведомлений', $output);
    }

    public function testExecuteWithoutExpiringCourses(): void
    {
        // Удаляем все транзакции, которые истекают завтра
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $tomorrowStart = $tomorrow->setTime(0, 0, 0);
        $tomorrowEnd = $tomorrow->setTime(23, 59, 59);
        
        $expiringTransactions = $this->transactionRepository->findExpiringTransactions($tomorrowStart, $tomorrowEnd);
        foreach ($expiringTransactions as $transaction) {
            $this->em->remove($transaction);
        }
        $this->em->flush();
        
        // Выполняем команду
        $exitCode = $this->commandTester->execute([]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем вывод
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Курсов с истекающей завтра арендой не найдено', $output);
    }

    public function testExecuteWithRealEmailOption(): void
    {
        // Выполняем команду с опцией --real-email
        $exitCode = $this->commandTester->execute(['--real-email' => true]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем, что письма отправлены
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправлено уведомлений', $output);
    }

    public function testExecuteWithBothOption(): void
    {
        // Выполняем команду с опцией --both
        $exitCode = $this->commandTester->execute(['--both' => true]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем, что письма отправлены
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправлено уведомлений', $output);
    }

    public function testGroupingTransactionsByUser(): void
    {
        // Создаем дополнительные транзакции для одного пользователя
        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'test@example.com']);
        $course = $this->em->getRepository(\App\Entity\Course::class)->findOneBy(['code' => 'javascript-advanced']);
        
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setCourse($course);
        $transaction->setType(1); // PAYMENT_TYPE
        $transaction->setAmount($course->getPrice());
        $transaction->setCreatedAt(new \DateTimeImmutable('-5 days'));
        $transaction->setExpiresAt($tomorrow->setTime(12, 0, 0));
        $this->em->persist($transaction);
        $this->em->flush();
        
        // Выполняем команду
        $exitCode = $this->commandTester->execute([]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем, что транзакции сгруппированы по пользователям
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('test@example.com', $output);
    }
} 