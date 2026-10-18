<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the redirections table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE akawaka_seo_redirect (id INT AUTO_INCREMENT NOT NULL, source_path VARCHAR(255) NOT NULL, target_url VARCHAR(2048) NOT NULL, status INT NOT NULL, enabled TINYINT(1) NOT NULL, automatic TINYINT(1) NOT NULL, hits INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_AKAWAKA_SEO_REDIRECT_SOURCE (source_path), PRIMARY KEY (id)) DEFAULT CHARACTER SET UTF8');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE akawaka_seo_redirect');
    }
}
