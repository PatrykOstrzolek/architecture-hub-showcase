<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Infrastructure\Sulu\PropertyResolver;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Cache\QuestionSetCacheKey;
use App\Assessment\Infrastructure\Sulu\PropertyResolver\QuestionSetSelectionPropertyResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * This is the test that actually enforces the security property the old
 * ExerciseAnswerRedactionSubscriber used to: isCorrect/explanation must
 * never appear in headless-resolved output.
 */
#[CoversClass(QuestionSetSelectionPropertyResolver::class)]
final class QuestionSetSelectionPropertyResolverTest extends TestCase
{
    private QuestionSetRepositoryInterface&MockObject $questionSets;
    private CacheInterface&MockObject $cache;
    private QuestionSetSelectionPropertyResolver $resolver;
    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->questionSets = $this->createMock(QuestionSetRepositoryInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->resolver = new QuestionSetSelectionPropertyResolver($this->questionSets, $this->cache);
        $this->fieldMetadata = new FieldMetadata('questionSet');
    }

    public function testResolveConsultsCacheUsingQuestionSetCacheKeyAndReturnsCallbackResult(): void
    {
        $questionSet = new QuestionSet('Distributed Systems Quiz');

        $this->cache->expects(self::once())->method('get')
            ->with(
                QuestionSetCacheKey::for(42),
                self::isCallable(),
            )
            ->willReturnCallback(
                fn (string $key, callable $callback) => $callback($this->createStub(ItemInterface::class)),
            );
        $this->questionSets->expects(self::once())->method('findWithQuestions')->with(42)->willReturn($questionSet);

        $view = $this->resolver->resolve(42, $this->fieldMetadata, 'en');

        /** @var array{title: string} $content */
        $content = $view->getContent();
        self::assertSame('Distributed Systems Quiz', $content['title']);
    }

    public function testResolveConfiguresCacheItemWithTtlBackstopOnCacheMiss(): void
    {
        $questionSet = new QuestionSet('Distributed Systems Quiz');
        $this->questionSets->method('findWithQuestions')->with(42)->willReturn($questionSet);

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(300);

        $this->cache->expects(self::once())->method('get')->willReturnCallback(
            static fn (string $key, callable $callback) => $callback($item),
        );

        $this->resolver->resolve(42, $this->fieldMetadata, 'en');
    }

    public function testRepeatResolveWithinCacheDoesNotReinvokeFindWithQuestions(): void
    {
        $questionSet = new QuestionSet('Cached Quiz');
        $cachedContent = [
            'id' => 42,
            'title' => $questionSet->getTitle(),
            'questions' => [],
        ];

        // Simulates the cache already holding a value for this key: the
        // callback passed to get() is never invoked, so findWithQuestions()
        // must not be called either.
        $this->cache->method('get')->with(QuestionSetCacheKey::for(42), self::isCallable())->willReturn($cachedContent);
        $this->questionSets->expects(self::never())->method('findWithQuestions');

        $view = $this->resolver->resolve(42, $this->fieldMetadata, 'en');

        self::assertSame($cachedContent, $view->getContent());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testResolvesQuestionSetWithoutLeakingAnswerKey(): void
    {
        $questionSet = new QuestionSet('Distributed Systems Quiz');
        $question = new Question('What does CAP stand for?', 'CAP = Consistency, Availability, Partition tolerance.');
        $question->addOption('Consistency, Availability, Partition tolerance', true);
        $question->addOption('Something else', false);
        $questionSet->addQuestion($question);

        $this->cache->method('get')->willReturnCallback(
            fn (string $key, callable $callback) => $callback($this->createStub(ItemInterface::class)),
        );
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

    #[AllowMockObjectsWithoutExpectations]
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
