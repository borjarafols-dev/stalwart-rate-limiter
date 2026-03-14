<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314003143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create provider_state table for rate level tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE provider_state (provider VARCHAR(64) NOT NULL, current_level INT NOT NULL, fail_count INT NOT NULL, success_count INT NOT NULL, last_change TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_event TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_rate VARCHAR(16) NOT NULL, last_concurrency INT NOT NULL, PRIMARY KEY (provider))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE provider_state');
    }
}
