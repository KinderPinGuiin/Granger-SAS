<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210722121648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE documents (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, step VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE uploaded_documents (id INT AUTO_INCREMENT NOT NULL, document_id INT DEFAULT NULL, user_id INT DEFAULT NULL, accepted TINYINT(1) DEFAULT NULL, INDEX IDX_252AB776C33F7837 (document_id), INDEX IDX_252AB776A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE uploaded_documents ADD CONSTRAINT FK_252AB776C33F7837 FOREIGN KEY (document_id) REFERENCES documents (id)');
        $this->addSql('ALTER TABLE uploaded_documents ADD CONSTRAINT FK_252AB776A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE validation_request DROP INDEX UNIQ_2A12D291A76ED395, ADD INDEX IDX_2A12D291A76ED395 (user_id)');
        $this->addSql('ALTER TABLE validation_request CHANGE accepted accepted TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE uploaded_documents DROP FOREIGN KEY FK_252AB776C33F7837');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE uploaded_documents');
        $this->addSql('ALTER TABLE validation_request DROP INDEX IDX_2A12D291A76ED395, ADD UNIQUE INDEX UNIQ_2A12D291A76ED395 (user_id)');
        $this->addSql('ALTER TABLE validation_request CHANGE accepted accepted TINYINT(1) NOT NULL');
    }
}
