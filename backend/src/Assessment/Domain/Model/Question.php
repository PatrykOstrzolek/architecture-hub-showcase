<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Standalone, reusable aggregate root — a Question does not know which
 * QuestionSet(s) it belongs to. Owns its Options (true composition: an
 * Option cannot exist without its Question, and is never shared).
 */
#[ORM\Entity]
#[ORM\Table(name: 'assessment_question')]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $text;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $explanation;

    /**
     * @var Collection<int, Option>
     */
    #[ORM\OneToMany(targetEntity: Option::class, mappedBy: 'question', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $options;

    public function __construct(string $text, ?string $explanation)
    {
        $this->text = $text;
        $this->explanation = $explanation;
        $this->options = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function addOption(string $text, bool $isCorrect, ?int $position = null): Option
    {
        $option = new Option($this, $text, $isCorrect, $position ?? $this->options->count());
        $this->options->add($option);

        return $option;
    }

    public function update(string $text, ?string $explanation): void
    {
        $this->text = $text;
        $this->explanation = $explanation;
    }

    /**
     * Clears and rebuilds all Options — matches this project's "clear and
     * rebuild rather than diff" convention for admin form saves.
     *
     * @param list<array{text: string, isCorrect: bool}> $options
     */
    public function replaceOptions(array $options): void
    {
        $this->options->clear();
        foreach ($options as $position => $option) {
            $this->addOption($option['text'], $option['isCorrect'], $position);
        }
    }

    /**
     * @return list<Option>
     */
    public function getOptions(): array
    {
        return \array_values($this->options->toArray());
    }

    /**
     * @return list<int>
     */
    public function getCorrectOptionIds(): array
    {
        $ids = [];
        foreach ($this->options as $option) {
            if ($option->isCorrect() && null !== $option->getId()) {
                $ids[] = $option->getId();
            }
        }

        return $ids;
    }
}
