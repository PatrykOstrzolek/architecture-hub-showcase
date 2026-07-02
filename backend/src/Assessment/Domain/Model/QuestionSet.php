<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Standalone aggregate root. Owns membership/order (QuestionSetItem), never
 * Question content — QuestionSetItem references a Question by identity
 * only, which is what lets one Question sit in many QuestionSets.
 */
#[ORM\Entity]
#[ORM\Table(name: 'assessment_question_set')]
class QuestionSet implements Identifiable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    /**
     * @var Collection<int, QuestionSetItem>
     */
    #[ORM\OneToMany(targetEntity: QuestionSetItem::class, mappedBy: 'questionSet', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct(string $title)
    {
        $this->title = $title;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function update(string $title): void
    {
        $this->title = $title;
    }

    public function addQuestion(Question $question, ?int $position = null): void
    {
        $this->items->add(new QuestionSetItem($this, $question, $position ?? $this->items->count()));
    }

    /**
     * Clears and rebuilds membership/order — matches this project's "clear
     * and rebuild rather than diff" convention for admin form saves.
     *
     * @param list<Question> $questions
     */
    public function replaceQuestions(array $questions): void
    {
        $this->items->clear();
        foreach ($questions as $position => $question) {
            $this->addQuestion($question, $position);
        }
    }

    /**
     * @return list<Question>
     */
    public function getOrderedQuestions(): array
    {
        $items = $this->items->toArray();
        \usort($items, static fn (QuestionSetItem $a, QuestionSetItem $b): int => $a->getPosition() <=> $b->getPosition());

        return \array_map(static fn (QuestionSetItem $item): Question => $item->getQuestion(), $items);
    }
}
