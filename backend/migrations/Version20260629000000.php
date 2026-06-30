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

    public function isTransactional(): bool
    {
        // Disable Doctrine's surrounding transaction: the seed SQL sets
        // session_replication_role which conflicts with an open transaction,
        // and a transaction abort would silently swallow exec() errors.
        return false;
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

        // addSql() uses PDO prepared statements which reject multi-statement SQL.
        // Use exec() on the native connection to run the entire dump in one shot.
        $pdo = $this->connection->getNativeConnection();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec($sql);
        // The dump sets search_path='' which would break Doctrine's own queries
        // on this connection after exec() returns.
        $pdo->exec("SET search_path TO public");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Seed data cannot be rolled back automatically.');
    }
}
