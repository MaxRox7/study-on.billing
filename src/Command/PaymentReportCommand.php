<?php

namespace App\Command;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\MailerSwitcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsCommand(
    name: 'payment:report',
    description: 'Генерирует и отправляет отчет по оплаченным курсам за месяц',
)]
class PaymentReportCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private MailerSwitcher $mailerSwitcher,
        private Environment $twig,
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start-date', null, InputOption::VALUE_REQUIRED, 'Дата начала периода (формат: Y-m-d)')
            ->addOption('end-date', null, InputOption::VALUE_REQUIRED, 'Дата окончания периода (формат: Y-m-d)')
            ->addOption('month', null, InputOption::VALUE_REQUIRED, 'Месяц для отчета (формат: Y-m), например: 2025-05')
            ->addOption('real-email', null, InputOption::VALUE_NONE, 'Отправить реальное письмо через Gmail')
            ->addOption('both', null, InputOption::VALUE_NONE, 'Отправить и в MailHog и через Gmail')
            ->setHelp('
Генерирует отчет по оплаченным курсам за указанный период.

Примеры использования:

1. Отчет за предыдущий месяц в MailHog (по умолчанию):
   docker-compose exec php bin/console payment:report

2. Отправить реальное письмо через Gmail:
   docker-compose exec php bin/console payment:report --real-email

3. Отправить и в MailHog и через Gmail одновременно:
   docker-compose exec php bin/console payment:report --both

4. Отчет за определенный период:
   docker-compose exec php bin/console payment:report --start-date=2025-05-01 --end-date=2025-05-31

5. Отчет за определенный месяц:
  docker-compose exec php bin/console payment:report --month=2025-05
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Генерация отчета по оплаченным курсам');

        // Определяем период отчета
        [$startDate, $endDate] = $this->determinePeriod($input, $io);

        $io->writeln(sprintf('Период отчета: %s - %s', 
            $startDate->format('d.m.Y'), 
            $endDate->format('d.m.Y')
        ));

        // Запрос всех оплат за период
        $transactions = $this->transactionRepository->findPaymentTransactionsForPeriod($startDate, $endDate);

        if (empty($transactions)) {
            $io->warning('За указанный период оплат не найдено.');
        }

        // Группируем по курсам и типам
        $courseStats = [];
        $totalAmount = 0;

        foreach ($transactions as $transaction) {
            $course = $transaction->getCourse();
            $courseCode = $course->getCode();
            $courseTitle = $course->getTitle();
            $courseType = $this->getCourseTypeName($course->getType());
            
            if (!isset($courseStats[$courseCode])) {
                $courseStats[$courseCode] = [
                    'title' => $courseTitle,
                    'type' => $courseType,
                    'rent_count' => 0,
                    'buy_count' => 0,
                    'total_amount' => 0
                ];
            }

            // Увеличиваем счетчики
            if ($course->getType() === Course::TYPE_RENT) {
                $courseStats[$courseCode]['rent_count']++;
            } elseif ($course->getType() === Course::TYPE_BUY) {
                $courseStats[$courseCode]['buy_count']++;
            }

            $courseStats[$courseCode]['total_amount'] += abs($transaction->getAmount());
            $totalAmount += abs($transaction->getAmount());
        }

        try {
            $this->sendReport($courseStats, $totalAmount, $startDate, $endDate, $input);
            $io->success('Отчет успешно отправлен на указанную в конфиге почту.');
        } catch (\Exception $e) {
            $io->error(sprintf('Ошибка отправки отчета: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function determinePeriod(InputInterface $input, SymfonyStyle $io): array
    {
        $startDateOption = $input->getOption('start-date');
        $endDateOption = $input->getOption('end-date');
        $monthOption = $input->getOption('month');

        // Если указаны start-date и end-date
        if ($startDateOption && $endDateOption) {
            try {
                $startDate = new \DateTimeImmutable($startDateOption);
                $endDate = new \DateTimeImmutable($endDateOption . ' 23:59:59');
                
                if ($startDate > $endDate) {
                    throw new \InvalidArgumentException('Дата начала не может быть больше даты окончания.');
                }
                
                return [$startDate, $endDate];
            } catch (\Exception $e) {
                $io->error('Неверный формат даты. Используйте формат Y-m-d (например: 2025-05-01)');
                throw $e;
            }
        }

        // Если указан месяц
        if ($monthOption) {
            try {
                $date = \DateTimeImmutable::createFromFormat('Y-m', $monthOption);
                if (!$date) {
                    throw new \InvalidArgumentException('Неверный формат месяца');
                }
                
                $startDate = $date->modify('first day of this month')->setTime(0, 0, 0);
                $endDate = $date->modify('last day of this month')->setTime(23, 59, 59);
                
                return [$startDate, $endDate];
            } catch (\Exception $e) {
                $io->error('Неверный формат месяца. Используйте формат Y-m (например: 2025-05)');
                throw $e;
            }
        }

        // По умолчанию - предыдущий месяц
        $now = new \DateTimeImmutable();
        $startDate = $now->modify('first day of last month')->setTime(0, 0, 0);
        $endDate = $now->modify('last day of last month')->setTime(23, 59, 59);
        
        return [$startDate, $endDate];
    }

    private function getCourseTypeName(int $type): string
    {
        return match ($type) {
            Course::TYPE_RENT => 'аренда',
            Course::TYPE_BUY => 'покупка',
            Course::TYPE_FREE => 'бесплатный',
            default => 'неизвестно'
        };
    }

    private function sendReport(array $courseStats, float $totalAmount, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate, InputInterface $input): void
    {
        // Получаем email получателя из конфига (можно добавить в services.yaml)
        $reportEmail = $this->parameterBag->get('app.report_email') ?? 'admin@study-on.local';

        // Создаем HTML письма
        $htmlBody = $this->twig->render('email/payment_report.html.twig', [
            'courseStats' => $courseStats,
            'totalAmount' => $totalAmount,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);

        $email = (new Email())
            ->from('noreply@study-on.local')
            ->to($reportEmail)
            ->subject(sprintf('Отчет об оплаченных курсах за период %s - %s', 
                $startDate->format('d.m.Y'), 
                $endDate->format('d.m.Y')
            ))
            ->html($htmlBody);

        // Выбираем способ отправки на основе опций
        if ($input->getOption('both')) {
            $this->mailerSwitcher->sendBoth($email);
        } else {
            $useRealEmail = $input->getOption('real-email');
            $this->mailerSwitcher->send($email, $useRealEmail);
        }
    }
} 