<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Infrastructure\Sulu\PropertyResolver;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Sulu\PropertyResolver\QuestionSetSelectionPropertyResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;

/**
 * This is the test that actually enforces the security property the old
 * ExerciseAnswerRedactionSubscriber used to: isCorrect/explanation must
 * never appear in headless-resolved output.
 */
#[CoversClass(QuestionSetSelectionPropertyResolver::class)]
final class QuestionSetSelectionPropertyResolverTest extends TestCase
{
    private QuestionSetRepositoryInterface&MockObject $questionSets;
    private QuestionSetSelectionPropertyResolver $resolver;
    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->questionSets = $this->createMock(QuestionSetRepositoryInterface::class);
        $this->resolver = new QuestionSetSelectionPropertyResolver($this->questionSets);
        $this->fieldMetadata = new FieldMetadata('questionSet');
    }

    public function testResolvesQuestionSetWithoutLeakingAnswerKey(): void
    {
        $questionSet = new QuestionSet('Distributed Systems Quiz');
        $question = new Question('What does CAP stand for?', 'CAP = Consistency, Availability, Partition tolerance.');
        $question->addOption('Consistency, Availability, Partition tolerance', true);
        $question->addOption('Something else', false);
        $questionSet->addQuestion($question);

        $this->questionSets->method('findWithQuestions')->with(42)->willReturn($questionSet);

        $view = $this->resolver->resolve(42, $this->fieldMetadata, 'en');

        /** @var array{title: string, questions: list<array<string, mixed>>} $content */
        $content = $view->getContent();

        self::assertSame('Distributed Systems Quiz', $content['title']);
        self::assertCount(1, $content['questions']);

        /** @var array<string, mixed> $resolvedQuestion */
        $resolvedQuestion = $content['questions'][0];
        self::assertSame('What does CAP stand for?', $resolvedQuestion['text']);
        self::assertArrayNotHasKey('explanation', $resolvedQuestion);

        /** @var list<array<string, mixed>> $options */
        $options = $resolvedQuestion['options'];
        self::assertCount(2, $options);

        foreach ($options as $option) {
            self::assertArrayHasKey('id', $option);
            self::assertArrayHasKey('text', $option);
            self::assertArrayNotHasKey('isCorrect', $option);
        }
    }

    public function testNonIntegerDataResolvesToNull(): void
    {
        $this->questionSets->expects(self::never())->method('findWithQuestions');

        $view = $this->resolver->resolve('not-an-id', $this->fieldMetadata, 'en');

        self::assertNull($view->getContent());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUnknownQuestionSetIdResolvesToNull(): void
    {
        $this->questionSets->method('findWithQuestions')->with(99)->willReturn(null);

        $view = $this->resolver->resolve(99, $this->fieldMetadata, 'en');

        self::assertNull($view->getContent());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetContentTypeReturnsSingleQuestionSetSelection(): void
    {
        self::assertSame('single_question_set_selection', QuestionSetSelectionPropertyResolver::getContentType());
    }
}
