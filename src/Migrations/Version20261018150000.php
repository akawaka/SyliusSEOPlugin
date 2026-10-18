<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the SEO metadata table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE akawaka_seo_metadata (id INT AUTO_INCREMENT NOT NULL, subject_type VARCHAR(50) NOT NULL, subject_id INT NOT NULL, locale VARCHAR(10) NOT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description LONGTEXT DEFAULT NULL, canonical_url VARCHAR(2048) DEFAULT NULL, robots_index TINYINT(1) NOT NULL, robots_follow TINYINT(1) NOT NULL, og_title VARCHAR(255) DEFAULT NULL, og_description LONGTEXT DEFAULT NULL, og_image VARCHAR(2048) DEFAULT NULL, UNIQUE INDEX akawaka_seo_subject_locale (subject_type, subject_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET UTF8');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE akawaka_seo_metadata');
    }
}
