<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'exercise_attempt')]
#[ORM\Index(columns: ['exercise_uuid'], name: 'idx_exercise_attempt_exercise_uuid')]
#[ORM\Index(columns: ['session_id'], name: 'idx_exercise_attempt_session_id')]
class Attempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36)]
    private string $exerciseUuid;

    #[ORM\Column(type: 'string', length: 36)]
    private string $sessionId;

    /**
     * @var list<string|null>
     */
    #[ORM\Column(type: 'json')]
    private array $answers;

    #[ORM\Column(type: 'integer')]
    private int $score;

    #[ORM\Column(type: 'integer')]
    private int $total;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<string|null> $answers
     */
    public function __construct(string $exerciseUuid, string $sessionId, array $answers, int $score, int $total)
    {
        $this->exerciseUuid = $exerciseUuid;
        $this->sessionId = $sessionId;
        $this->answers = $answers;
        $this->score = $score;
        $this->total = $total;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExerciseUuid(): string
    {
        return $this->exerciseUuid;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
