<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210626191005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO contenu (page, content)
                       VALUES ('accept_mail', 'Bonjour,<br/>Votre candidature a été acceptée'),
                       ('deny_mail', 'Bonjour,<br/>Votre candidature a été refusée')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM contenu
                       WHERE page = 'accept_mail' OR page='deny_mail'");
    }
}
