<?php

namespace App\Command;

use App\Entity\Transaction;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Отправляет уведомления о курсах, срок аренды которых заканчивается завтра',
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private Environment $twig
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Отправка уведомлений об окончании срока аренды курсов');

        // Находим транзакции с арендованными курсами, срок которых истекает завтра
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $tomorrowStart = $tomorrow->setTime(0, 0, 0);
        $tomorrowEnd = $tomorrow->setTime(23, 59, 59);

        $qb = $this->em->getRepository(Transaction::class)->createQueryBuilder('t')
            ->innerJoin('t.user', 'u')
            ->innerJoin('t.course', 'c')
            ->where('t.type = :payment_type')
            ->andWhere('t.expiresAt BETWEEN :start AND :end')
            ->andWhere('t.amount < 0') // только списания (платежи)
            ->setParameter('payment_type', PaymentService::TYPE_PAYMENT)
            ->setParameter('start', $tomorrowStart)
            ->setParameter('end', $tomorrowEnd);

        $expiringTransactions = $qb->getQuery()->getResult();

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
                $this->sendNotification($userData['user'], $userData['transactions']);
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

    private function sendNotification($user, array $transactions): void
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

        $this->mailer->send($email);
    }
} 