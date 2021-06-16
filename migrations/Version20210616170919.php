<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210616170919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B8A832C1C9');
        $this->addSql('DROP INDEX IDX_E33BD3B8A832C1C9 ON candidature');
        $this->addSql('ALTER TABLE candidature CHANGE email_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_E33BD3B8A76ED395 ON candidature (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B8A76ED395');
        $this->addSql('DROP INDEX IDX_E33BD3B8A76ED395 ON candidature');
        $this->addSql('ALTER TABLE candidature CHANGE user_id email_id INT NOT NULL');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B8A832C1C9 FOREIGN KEY (email_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_E33BD3B8A832C1C9 ON candidature (email_id)');
    }
}
