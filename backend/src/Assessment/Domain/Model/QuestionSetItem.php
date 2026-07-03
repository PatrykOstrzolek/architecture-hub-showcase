<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Join entity giving QuestionSet<->Question a many-to-many relationship
 * *with* an explicit per-set order — plain #[ORM\ManyToMany] can't express
 * a custom order per relationship instance. References a Question by
 * identity only (no cascade-remove): removing an item drops that question
 * from this set, it never deletes the Question itself.
 */
#[ORM\Entity]
#[ORM\Table(name: 'assessment_question_set_item')]
#[ORM\Index(columns: ['question_set_id', 'position'], name: 'idx_question_set_item_set_position')]
class QuestionSetItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QuestionSet::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private QuestionSet $questionSet;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Question $question;

    #[ORM\Column(type: 'integer')]
    private int $position;

    public function __construct(QuestionSet $questionSet, Question $question, int $position)
    {
        $this->questionSet = $questionSet;
        $this->question = $question;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
