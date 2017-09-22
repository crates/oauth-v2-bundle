<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170921095053 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('
            ALTER TABLE `credentials` ADD ( 
                `auth_url` TEXT NULL,
                `token_url` VARCHAR(255) NULL,
                `request_token_url` VARCHAR(255) NULL,
                `app_key` VARCHAR(255) NULL,
                `app_secret` VARCHAR(255) NULL,
                `app_secret_docker` VARCHAR(255) NULL
            );
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
