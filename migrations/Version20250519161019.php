<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250519161019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE course (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, type SMALLINT NOT NULL, price DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_COURSE_CODE ON course (code)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE transaction (id SERIAL NOT NULL, user_id INT NOT NULL, course_id INT DEFAULT NULL, type SMALLINT NOT NULL, amount DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_723705D1A76ED395 ON transaction (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_723705D1591CC992 ON transaction (course_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN transaction.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN transaction.expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction ADD CONSTRAINT FK_723705D1A76ED395 FOREIGN KEY (user_id) REFERENCES billing_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction ADD CONSTRAINT FK_723705D1591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction DROP CONSTRAINT FK_723705D1A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction DROP CONSTRAINT FK_723705D1591CC992
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE course
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE transaction
        SQL);
    }
}
