<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed initial demo data. Admin password is disabled (!!); reset with sulu:security:user:change-password admin';
    }

    public function up(Schema $schema): void
    {
        $compressed = \file_get_contents(__DIR__ . '/data/seed.sql.gz');
        if (false === $compressed) {
            throw new \RuntimeException('Cannot read migrations/data/seed.sql.gz');
        }
        $sql = \gzdecode($compressed);
        if (false === $sql) {
            throw new \RuntimeException('Failed to decompress seed.sql.gz');
        }

        // Strip psql metacommands (lines starting with \) — not valid SQL
        $sql = (string) \preg_replace('/^\\\\.*$/m', '', $sql);

        $this->addSql($sql);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Seed data cannot be rolled back automatically.');
    }
}
