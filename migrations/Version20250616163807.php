<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616163807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Таблица billing_user была создана в более ранних миграциях.
        // Добавляем недостающий столбец balance, если его ещё нет.
        $this->addSql(<<<'SQL'
            ALTER TABLE billing_user
            ADD COLUMN IF NOT EXISTS balance NUMERIC(10,2) DEFAULT '0' NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Удаляем добавленный столбец при откате
        $this->addSql(<<<'SQL'
            ALTER TABLE billing_user
            DROP COLUMN IF EXISTS balance
        SQL);
    }
}
