<?php

namespace App\Command;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\MailerSwitcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Отправляет уведомления о курсах, срок аренды которых заканчивается завтра',
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private MailerSwitcher $mailerSwitcher,
        private Environment $twig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('real-email', null, InputOption::VALUE_NONE, 'Отправить реальные письма через Gmail')
            ->addOption('both', null, InputOption::VALUE_NONE, 'Отправить и в MailHog и через Gmail')
            ->setHelp('
Отправляет уведомления пользователям о курсах, срок аренды которых истекает завтра.

Примеры использования:

1. Отправить уведомления в MailHog (по умолчанию):
   docker-compose exec php bin/console payment:ending:notification

2. Отправить реальные письма через Gmail:
   docker-compose exec php bin/console payment:ending:notification --real-email

3. Отправить и в MailHog и через Gmail одновременно:
   docker-compose exec php bin/console payment:ending:notification --both
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Отправка уведомлений об окончании срока аренды курсов');

        // Находим транзакции с арендованными курсами, срок которых истекает завтра
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $tomorrowStart = $tomorrow->setTime(0, 0, 0);
        $tomorrowEnd = $tomorrow->setTime(23, 59, 59);

        $expiringTransactions = $this->transactionRepository->findExpiringTransactions($tomorrowStart, $tomorrowEnd);

        if (empty($expiringTransactions)) {
            $io->success('Курсов с истекающей завтра арендой не найдено.');
            return Command::SUCCESS;
        }

        // Группируем транзакции по пользователям
        $userTransactions = [];
        foreach ($expiringTransactions as $transaction) {
            $userId = $transaction->getUser()->getId();
            if (!isset($userTransactions[$userId])) {
                $userTransactions[$userId] = [
                    'user' => $transaction->getUser(),
                    'transactions' => []
                ];
            }
            $userTransactions[$userId]['transactions'][] = $transaction;
        }

        $sentCount = 0;
        foreach ($userTransactions as $userData) {
            try {
                $this->sendNotification($userData['user'], $userData['transactions'], $input);
                $sentCount++;
                $io->writeln(sprintf('Уведомление отправлено пользователю: %s', $userData['user']->getEmail()));
            } catch (\Exception $e) {
                $io->error(sprintf('Ошибка отправки уведомления пользователю %s: %s', 
                    $userData['user']->getEmail(), $e->getMessage()));
            }
        }

        $io->success(sprintf('Отправлено уведомлений: %d из %d', $sentCount, count($userTransactions)));

        return Command::SUCCESS;
    }

    private function sendNotification($user, array $transactions, InputInterface $input): void
    {
        // Формируем данные о курсах
        $courses = [];
        foreach ($transactions as $transaction) {
            $course = $transaction->getCourse();
            $courses[] = [
                'name' => $course->getTitle(),
                'expires_at' => $transaction->getExpiresAt()
            ];
        }

        // Создаем HTML письма
        $htmlBody = $this->twig->render('email/expiring_courses.html.twig', [
            'user' => $user,
            'courses' => $courses
        ]);

        $email = (new Email())
            ->from('noreply@study-on.local')
            ->to($user->getEmail())
            ->subject('Уведомление об окончании срока аренды курсов')
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