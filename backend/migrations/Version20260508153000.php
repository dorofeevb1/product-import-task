<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auth users and refresh token tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE auth_users (id SERIAL NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_auth_users_email ON auth_users (email)');
        $this->addSql('CREATE TABLE auth_refresh_tokens (token_hash VARCHAR(80) NOT NULL, subject VARCHAR(180) NOT NULL, expires_at INT NOT NULL, created_at INT NOT NULL, PRIMARY KEY(token_hash))');
        $this->addSql('CREATE INDEX idx_auth_refresh_tokens_user ON auth_refresh_tokens (subject)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE auth_refresh_tokens');
        $this->addSql('DROP TABLE auth_users');
    }
}
