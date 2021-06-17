<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210617151101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            INSERT INTO contenu (page, content)
            VALUES
            ("home", "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque nec laoreet ante, nec lacinia augue. Suspendisse potenti. Pellentesque in libero eget elit porta efficitur fringilla nec felis. Vivamus leo sem, convallis ac magna sit amet..."),
            ("about", "Rien du tout")
        ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DELETE FROM contenu WHERE page="home" OR page="about"');
    }
}
