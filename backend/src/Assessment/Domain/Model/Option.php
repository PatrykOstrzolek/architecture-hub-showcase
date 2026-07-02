<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'assessment_option')]
class Option
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false)]
    private Question $question;

    #[ORM\Column(type: 'text')]
    private string $text;

    #[ORM\Column(type: 'boolean')]
    private bool $isCorrect;

    #[ORM\Column(type: 'integer')]
    private int $position;

    public function __construct(Question $question, string $text, bool $isCorrect, int $position)
    {
        $this->question = $question;
        $this->text = $text;
        $this->isCorrect = $isCorrect;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
