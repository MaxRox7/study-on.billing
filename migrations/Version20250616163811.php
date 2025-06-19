<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616163811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Таблица billing_user уже создана предыдущей миграцией 20250616163807.
        // Чтобы избежать ошибки «relation already exists» при полном откате/пересборке БД,
        // эта миграция оставлена пустой.
    }

    public function down(Schema $schema): void
    {
        // Ничего не делаем. Обратное действие не требуется.
    }
}
