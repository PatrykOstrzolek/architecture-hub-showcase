<?php

declare(strict_types=1);

namespace App\Tests\Functional\Assessment\Infrastructure\Doctrine;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Model\QuestionSetItem;
use App\Assessment\Infrastructure\Doctrine\DoctrineQuestionSetRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\AbstractLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The domain's first functional/KernelTestCase test. Persists a small
 * QuestionSet -> QuestionSetItem -> Question -> Option fixture tree inline
 * (no DataFixtures class - not justified for one narrowly-scoped test) and
 * asserts findWithQuestions() both hydrates it correctly and issues exactly
 * one SQL query, guarding against a future silent N+1/fan-out regression on
 * the domain's most call-site-frequent query.
 */
#[CoversClass(DoctrineQuestionSetRepository::class)]
final class DoctrineQuestionSetRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ?int $questionSetId = null;

    /**
     * @var list<int>
     */
    private array $questionIds = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        // No DataFixtures/purge convention exists yet in this codebase (see
        // class docblock) - clean up the rows this test created directly,
        // in FK-safe order, so repeated runs don't accumulate data.
        if (null !== $this->questionSetId) {
            $connection = $this->entityManager->getConnection();

            if ([] !== $this->questionIds) {
                $connection->executeStatement(
                    \sprintf('DELETE FROM assessment_option WHERE question_id IN (%s)', \implode(',', $this->questionIds)),
                );
            }

            $connection->executeStatement(
                'DELETE FROM assessment_question_set_item WHERE question_set_id = :id',
                ['id' => $this->questionSetId],
            );

            if ([] !== $this->questionIds) {
                $connection->executeStatement(
                    \sprintf('DELETE FROM assessment_question WHERE id IN (%s)', \implode(',', $this->questionIds)),
                );
            }

            $connection->executeStatement(
                'DELETE FROM assessment_question_set WHERE id = :id',
                ['id' => $this->questionSetId],
            );
        }

        parent::tearDown();
    }

    public function testFindWithQuestionsHydratesOrderedTreeInExactlyOneQuery(): void
    {
        $questionSet = new QuestionSet('Functional Test Quiz');

        $questionTwo = new Question('Second question?', null);
        $questionTwo->addOption('Wrong', false);
        $questionTwo->addOption('Right', true);

        $questionOne = new Question('First question?', 'Because reasons');
        $questionOne->addOption('A', true);
        $questionOne->addOption('B', false);

        // Attach out of natural order (position 1, then position 0) so the
        // assertion below can't pass by accident of insertion order.
        $questionSet->addQuestion($questionTwo, 1);
        $questionSet->addQuestion($questionOne, 0);

        $this->entityManager->persist($questionSet);
        $this->entityManager->persist($questionOne);
        $this->entityManager->persist($questionTwo);
        $this->entityManager->flush();

        $questionSetId = $questionSet->getId();
        self::assertNotNull($questionSetId);
        $this->questionSetId = $questionSetId;

        $questionOneId = $questionOne->getId();
        $questionTwoId = $questionTwo->getId();
        self::assertNotNull($questionOneId);
        self::assertNotNull($questionTwoId);
        $this->questionIds = [$questionOneId, $questionTwoId];

        // Detach everything so the query below hydrates fresh from the
        // database rather than serving cached identity-map objects.
        $this->entityManager->clear();

        $logger = new class extends AbstractLogger {
            public int $queryCount = 0;

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $text = (string) $message;
                if (\str_starts_with($text, 'Executing statement:') || \str_starts_with($text, 'Executing query:')) {
                    ++$this->queryCount;
                }
            }
        };

        // The container's own DBAL connection has already been built by the
        // time this test runs (its driver is wrapped once, at construction),
        // so a Doctrine SQL logging middleware can't be retrofitted onto it.
        // Instead, open a second connection to the same test database - one
        // dedicated exclusively to the single call under test - wrapped with
        // a counting middleware, and reuse the container's ORM Configuration
        // (metadata/mapping) so it resolves entities identically.
        /** @var array<string, mixed> $connectionParams */
        $connectionParams = $this->entityManager->getConnection()->getParams();
        $dbalConfiguration = new DbalConfiguration();
        $dbalConfiguration->setMiddlewares([new LoggingMiddleware($logger)]);
        // @phpstan-ignore-next-line argument.type (getParams() is intentionally reused to open a diagnostic-only second connection to the same database)
        $loggedConnection = DriverManager::getConnection($connectionParams, $dbalConfiguration);
        $loggedEntityManager = new EntityManager($loggedConnection, $this->entityManager->getConfiguration());

        $repository = new DoctrineQuestionSetRepository($loggedEntityManager);

        $result = $repository->findWithQuestions($questionSetId);

        self::assertInstanceOf(QuestionSet::class, $result);
        self::assertSame('Functional Test Quiz', $result->getTitle());

        // Assert hydration order directly off the mapped `items` collection
        // (not getOrderedQuestions(), which defensively re-sorts) so this
        // specifically exercises the query's/mapping's own ordering, backed
        // by QuestionSetItem's #[ORM\OrderBy(['position' => 'ASC'])].
        $itemsProperty = new \ReflectionProperty(QuestionSet::class, 'items');
        $items = $itemsProperty->getValue($result);
        self::assertInstanceOf(Collection::class, $items);

        $hydratedQuestionIds = [];
        foreach ($items as $item) {
            self::assertInstanceOf(QuestionSetItem::class, $item);
            $questionId = $item->getQuestion()->getId();
            self::assertNotNull($questionId);
            $hydratedQuestionIds[] = $questionId;
        }
        self::assertSame([$questionOneId, $questionTwoId], $hydratedQuestionIds, 'items should be hydrated in position ASC order');

        $orderedQuestions = $result->getOrderedQuestions();
        self::assertCount(2, $orderedQuestions);

        self::assertSame($questionOneId, $orderedQuestions[0]->getId());
        self::assertSame('First question?', $orderedQuestions[0]->getText());
        $firstOptions = $orderedQuestions[0]->getOptions();
        self::assertCount(2, $firstOptions);
        self::assertSame('A', $firstOptions[0]->getText());
        self::assertTrue($firstOptions[0]->isCorrect());
        self::assertSame('B', $firstOptions[1]->getText());
        self::assertFalse($firstOptions[1]->isCorrect());

        self::assertSame($questionTwoId, $orderedQuestions[1]->getId());
        $secondOptions = $orderedQuestions[1]->getOptions();
        self::assertCount(2, $secondOptions);
        self::assertSame('Wrong', $secondOptions[0]->getText());
        self::assertSame('Right', $secondOptions[1]->getText());

        self::assertSame(1, $logger->queryCount, 'findWithQuestions() should issue exactly one SQL query.');
    }
}
