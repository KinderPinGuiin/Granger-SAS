<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210620100022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature ADD poste_id INT NOT NULL');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B8A0905086 FOREIGN KEY (poste_id) REFERENCES poste (id)');
        $this->addSql('CREATE INDEX IDX_E33BD3B8A0905086 ON candidature (poste_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B8A0905086');
        $this->addSql('DROP INDEX IDX_E33BD3B8A0905086 ON candidature');
        $this->addSql('ALTER TABLE candidature DROP poste_id');
    }
}
