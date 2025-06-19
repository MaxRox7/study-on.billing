<?php

namespace App\Tests\Command;

use App\Command\PaymentReportCommand;
use App\DataFixtures\CourseFixtures;
use App\DataFixtures\TransactionFixtures;
use App\DataFixtures\UserFixtures;
use App\Repository\TransactionRepository;
use App\Service\MailerSwitcher;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class PaymentReportCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TransactionRepository $transactionRepository;
    private MailerSwitcher $mailerSwitcher;
    private Environment $twig;
    private ParameterBagInterface $parameterBag;
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
        $this->parameterBag = $container->get(ParameterBagInterface::class);
        
        // Начинаем транзакцию для изоляции тестов
        $this->em->getConnection()->beginTransaction();
        
        // Загружаем фикстуры
        $this->loadFixtures();
        
        // Настраиваем команду
        $command = new PaymentReportCommand(
            $this->transactionRepository,
            $this->mailerSwitcher,
            $this->twig,
            $this->parameterBag
        );
        
        $application = new Application();
        $application->add($command);
        
        $command = $application->find('payment:report');
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

    public function testExecuteDefaultPeriod(): void
    {
        // Выполняем команду без параметров (отчет за прошлый месяц)
        $exitCode = $this->commandTester->execute([]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем вывод
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Генерация отчета по оплаченным курсам', $output);
        $this->assertStringContainsString('Период отчета:', $output);
        $this->assertStringContainsString('Отчет успешно отправлен', $output);
    }

    public function testExecuteWithSpecificDates(): void
    {
        $startDate = (new \DateTimeImmutable())->modify('-30 days')->format('Y-m-d');
        $endDate = (new \DateTimeImmutable())->format('Y-m-d');
        
        // Выполняем команду с указанным периодом
        $exitCode = $this->commandTester->execute([
            '--start-date' => $startDate,
            '--end-date' => $endDate
        ]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем вывод
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Период отчета:', $output);
        $this->assertStringContainsString($startDate, $output);
        $this->assertStringContainsString($endDate, $output);
    }

    public function testExecuteWithMonthOption(): void
    {
        $currentMonth = (new \DateTimeImmutable())->format('Y-m');
        
        // Выполняем команду для конкретного месяца
        $exitCode = $this->commandTester->execute([
            '--month' => $currentMonth
        ]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем вывод
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Генерация отчета по оплаченным курсам', $output);
        $this->assertStringContainsString('Период отчета:', $output);
    }

    public function testExecuteWithInvalidDateFormat(): void
    {
        // Выполняем команду с неверным форматом даты
        $exitCode = $this->commandTester->execute([
            '--start-date' => 'invalid-date',
            '--end-date' => '2025-05-31'
        ]);
        
        // Проверяем, что команда завершилась с ошибкой
        $this->assertEquals(1, $exitCode);
        
        // Проверяем сообщение об ошибке
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Неверный формат даты', $output);
    }

    public function testExecuteWithInvalidMonthFormat(): void
    {
        // Выполняем команду с неверным форматом месяца
        $exitCode = $this->commandTester->execute([
            '--month' => 'invalid-month'
        ]);
        
        // Проверяем, что команда завершилась с ошибкой
        $this->assertEquals(1, $exitCode);
        
        // Проверяем сообщение об ошибке
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Неверный формат месяца', $output);
    }

    public function testExecuteWithRealEmailOption(): void
    {
        // Выполняем команду с опцией --real-email
        $exitCode = $this->commandTester->execute(['--real-email' => true]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем вывод
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отчет успешно отправлен', $output);
    }

    public function testExecuteWithBothOption(): void
    {
        // Выполняем команду с опцией --both
        $exitCode = $this->commandTester->execute(['--both' => true]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем вывод
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отчет успешно отправлен', $output);
    }

    public function testReportIncludesAllTransactionTypes(): void
    {
        // Выполняем команду для текущего месяца
        $currentMonth = (new \DateTimeImmutable())->format('Y-m');
        $exitCode = $this->commandTester->execute([
            '--month' => $currentMonth
        ]);
        
        // Проверяем успешное выполнение
        $this->assertEquals(0, $exitCode);
        
        // Проверяем, что отчет отправлен
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отчет успешно отправлен', $output);
    }

    public function testExecuteWithStartDateGreaterThanEndDate(): void
    {
        // Выполняем команду с датой начала больше даты окончания
        $exitCode = $this->commandTester->execute([
            '--start-date' => '2025-06-01',
            '--end-date' => '2025-05-01'
        ]);
        
        // Проверяем, что команда завершилась с ошибкой
        $this->assertEquals(1, $exitCode);
        
        // Проверяем сообщение об ошибке
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Дата начала не может быть больше даты окончания', $output);
    }
} 