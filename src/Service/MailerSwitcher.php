<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MailerSwitcher
{
    private MailerInterface $mailhogMailer;
    private MailerInterface $gmailMailer;

    public function __construct(
        private LoggerInterface $logger,
        private ParameterBagInterface $parameterBag
    ) {
        // Создаем отдельные mailer'ы для каждого транспорта
        $this->mailhogMailer = new \Symfony\Component\Mailer\Mailer(
            Transport::fromDsn('smtp://mailhog:1025')
        );
        
        // Получаем Gmail DSN из переменных окружения
        $gmailDsn = $_ENV['GMAIL_DSN'] ?? $_SERVER['GMAIL_DSN'] ?? 'smtp://mailhog:1025';
        $this->gmailMailer = new \Symfony\Component\Mailer\Mailer(
            Transport::fromDsn($gmailDsn)
        );
    }

    public function send(Email $email, bool $useRealEmail = false): void
    {
        if ($useRealEmail) {
            // Отправляем через Gmail
            $this->gmailMailer->send($email);
            $this->logger->info('Email sent via Gmail', ['to' => $email->getTo()]);
        } else {
            // Отправляем через MailHog
            $this->mailhogMailer->send($email);
            $this->logger->info('Email sent via MailHog', ['to' => $email->getTo()]);
        }
    }

    public function sendBoth(Email $email): void
    {
        // Отправляем и в MailHog (для просмотра) и через Gmail (реально)
        $this->mailhogMailer->send(clone $email);
        $this->gmailMailer->send(clone $email);
        
        $this->logger->info('Email sent via both Gmail and MailHog', ['to' => $email->getTo()]);
    }
} 