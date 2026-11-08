<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users, products, product_attributes, product_images and import_jobs tables.';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE users (
    id INT AUTO_INCREMENT NOT NULL,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    roles JSON NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX uniq_users_email (email),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE products (
    id INT AUTO_INCREMENT NOT NULL,
    external_code VARCHAR(191) NOT NULL,
    name VARCHAR(500) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    price NUMERIC(12, 2) NOT NULL,
    purchase_price NUMERIC(12, 2) DEFAULT NULL,
    discount NUMERIC(6, 2) DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX uniq_products_external_code (external_code),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE product_attributes (
    id INT AUTO_INCREMENT NOT NULL,
    product_id INT NOT NULL,
    `key` VARCHAR(191) NOT NULL,
    `value` LONGTEXT DEFAULT NULL,
    INDEX idx_product_attributes_product (product_id),
    PRIMARY KEY(id),
    CONSTRAINT fk_product_attributes_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE product_images (
    id INT AUTO_INCREMENT NOT NULL,
    product_id INT NOT NULL,
    url LONGTEXT NOT NULL,
    path VARCHAR(500) DEFAULT NULL,
    position INT NOT NULL,
    INDEX idx_product_images_product (product_id),
    PRIMARY KEY(id),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE import_jobs (
    id INT AUTO_INCREMENT NOT NULL,
    uuid VARCHAR(36) NOT NULL,
    status VARCHAR(20) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    total_rows INT NOT NULL,
    processed_rows INT NOT NULL,
    success_rows INT NOT NULL,
    error_rows INT NOT NULL,
    errors JSON NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX uniq_import_jobs_uuid (uuid),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement('DROP TABLE import_jobs');
        $this->connection->executeStatement('DROP TABLE product_images');
        $this->connection->executeStatement('DROP TABLE product_attributes');
        $this->connection->executeStatement('DROP TABLE products');
        $this->connection->executeStatement('DROP TABLE users');
    }
}
