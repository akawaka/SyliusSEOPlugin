<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the image alt text table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE akawaka_seo_image_alt (id INT AUTO_INCREMENT NOT NULL, image_path VARCHAR(255) NOT NULL, locale VARCHAR(10) NOT NULL, alt VARCHAR(255) NOT NULL, UNIQUE INDEX akawaka_seo_image_alt_path_locale (image_path, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET UTF8');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE akawaka_seo_image_alt');
    }
}
