<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the robots.txt table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE akawaka_seo_robots_txt (id INT AUTO_INCREMENT NOT NULL, channel_code VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_AKAWAKA_SEO_ROBOTS_CHANNEL (channel_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET UTF8');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE akawaka_seo_robots_txt');
    }
}
