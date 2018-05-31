<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180531091708 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('ALTER TABLE `consumers` MODIFY `app_secret` VARCHAR(3000) NOT NULL');
        $this->addSql('ALTER TABLE `consumers` MODIFY `app_secret_docker` VARCHAR(3000) NOT NULL');
        $this->addSql('ALTER TABLE `credentials` MODIFY `app_secret` VARCHAR(3000) NULL');
        $this->addSql('ALTER TABLE `credentials` MODIFY `app_secret_docker` VARCHAR(3000) NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
