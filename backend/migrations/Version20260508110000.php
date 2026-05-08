<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create products, attributes, images and import tasks tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE products (id SERIAL NOT NULL, external_code VARCHAR(128) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT NOT NULL, price NUMERIC(10, 2) NOT NULL, discount NUMERIC(6, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_products_external_code ON products (external_code)');
        $this->addSql('CREATE INDEX idx_products_name ON products (name)');
        $this->addSql('CREATE INDEX idx_products_price ON products (price)');
        $this->addSql('CREATE TABLE product_attributes (id SERIAL NOT NULL, product_id INT NOT NULL, attr_key VARCHAR(255) NOT NULL, attr_value TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_43D3AE274584665A ON product_attributes (product_id)');
        $this->addSql('CREATE TABLE product_images (id SERIAL NOT NULL, product_id INT NOT NULL, url TEXT NOT NULL, path TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5CACA0214584665A ON product_images (product_id)');
        $this->addSql('CREATE TABLE import_tasks (id VARCHAR(36) NOT NULL, status VARCHAR(32) NOT NULL, processed_rows INT DEFAULT 0 NOT NULL, failed_rows INT DEFAULT 0 NOT NULL, errors JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE import_rate_limits (ip VARCHAR(64) NOT NULL, attempts INT NOT NULL, window_start INT NOT NULL, PRIMARY KEY(ip))');
        $this->addSql('ALTER TABLE product_attributes ADD CONSTRAINT FK_43D3AE274584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_images ADD CONSTRAINT FK_5CACA0214584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_attributes DROP CONSTRAINT FK_43D3AE274584665A');
        $this->addSql('ALTER TABLE product_images DROP CONSTRAINT FK_5CACA0214584665A');
        $this->addSql('DROP TABLE import_tasks');
        $this->addSql('DROP TABLE import_rate_limits');
        $this->addSql('DROP TABLE product_images');
        $this->addSql('DROP TABLE product_attributes');
        $this->addSql('DROP TABLE products');
    }
}
