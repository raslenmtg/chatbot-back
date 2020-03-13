<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200311161225 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE Firstlasttram_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE Firstlasttram (id INT NOT NULL, depart VARCHAR(255) NOT NULL, jour VARCHAR(255) NOT NULL, heure TIME(0) WITHOUT TIME ZONE NOT NULL, first BOOLEAN NOT NULL, PRIMARY KEY(id))');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE Firstlasttram_id_seq CASCADE');
        $this->addSql('CREATE TABLE reporting_heure (nb_nouv_user INT DEFAULT NULL, nb_user_contact INT DEFAULT NULL, nb_msg_user INT DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL)');
        $this->addSql('CREATE TABLE reporting_jour (nb_nouv_user INT DEFAULT NULL, nb_user_contact INT DEFAULT NULL, nb_msg_user INT DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL)');
        $this->addSql('CREATE TABLE reporting_mois (nb_nouv_user INT DEFAULT NULL, nb_user_contact INT DEFAULT NULL, nb_msg_user INT DEFAULT NULL, date TIME(0) WITHOUT TIME ZONE DEFAULT NULL)');
        $this->addSql('CREATE TABLE reporting_semaine (nb_nouv_user INT DEFAULT NULL, nb_user_contact INT DEFAULT NULL, nb_msg_user INT DEFAULT NULL, date TIME(0) WITHOUT TIME ZONE DEFAULT NULL)');
        $this->addSql('DROP TABLE Firstlasttram');
    }
}
