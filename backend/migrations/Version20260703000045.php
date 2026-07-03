<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing indexes on exercise_attempt.created_at and assessment_question_set_item (question_set_id, position) for the Assessment bounded context.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_exercise_attempt_created_at ON exercise_attempt (created_at)');
        $this->addSql('CREATE INDEX idx_question_set_item_set_position ON assessment_question_set_item (question_set_id, position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_exercise_attempt_created_at');
        $this->addSql('DROP INDEX idx_question_set_item_set_position');
    }
}
