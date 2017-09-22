<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170921093154 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('CREATE DATABASE IF NOT EXISTS `oauth_v2`');
        $this->addSql('USE `oauth_v2`');
        $this->addSql('
            CREATE TABLE IF NOT EXISTS `consumers` (
                `component_id` VARCHAR(255) NOT NULL,
                `auth_url` TEXT NOT NULL,
                `token_url` VARCHAR(255) NOT NULL,
                `request_token_url` VARCHAR(255) NULL,
                `app_key` VARCHAR(255) NOT NULL,
                `app_secret` VARCHAR(255) NOT NULL,
                `app_secret_docker` VARCHAR(255) NOT NULL,
                `friendly_name` VARCHAR(255) NOT NULL,
                `oauth_version` VARCHAR(255) NOT NULL,
                CONSTRAINT `unique_component_id` UNIQUE(`component_id`)
            ) ENGINE = InnoDB;
        ');

        $this->addSql('
            CREATE TABLE IF NOT EXISTS `credentials` (
                `id` VARCHAR(255) NOT NULL,
                `component_id` VARCHAR(255) NOT NULL,
                `project_id` VARCHAR(255) NOT NULL,
                `creator` VARCHAR(255) NOT NULL,
                `data` TEXT NOT NULL,
                `authorized_for` VARCHAR(255) NULL,
                `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY ( `component_id`, `project_id`, `id` ) 
            ) ENGINE = InnoDB;
        ');

        $this->addSql('
            CREATE TABLE IF NOT EXISTS `sessions` (
                `sess_id` VARBINARY(128) NOT NULL PRIMARY KEY,
                `sess_data` BLOB NOT NULL,
                `sess_time` INTEGER UNSIGNED NOT NULL,
                `sess_lifetime` MEDIUMINT NOT NULL
            ) COLLATE utf8_bin, ENGINE = InnoDB;
        ');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
