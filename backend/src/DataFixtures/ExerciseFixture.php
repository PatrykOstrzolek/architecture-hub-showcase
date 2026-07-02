<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Creates (or updates) one exercise page per learning path, at
 * /learning-paths/{slug}/exercise, each referencing a QuestionSet (see
 * ADR-0014) built from normalized Question/Option entities.
 * LearningPathFixture looks these pages up by convention slug and links
 * them via its `exercise` field — see ADR 0011.
 *
 * Run with: php -d memory_limit=1G bin/console doctrine:fixtures:load --append --group=dev.
 */
class ExerciseFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    use HandleTrait;

    /** @var array<string, array{title: string, intro: string, questions: list<array{text: string, explanation: string|null, options: list<array{text: string, isCorrect: bool}>}>}> slug => exercise content */
    private const EXERCISES = [
        'distributed-systems-fundamentals' => [
            'title' => 'Distributed Systems Fundamentals: Check Your Understanding',
            'intro' => 'Five questions covering the CAP theorem, consistency models, and the resilience and messaging patterns from this learning path.',
            'questions' => [
                [
                    'text' => 'During a network partition, the CAP theorem forces a distributed system to choose between which two guarantees?',
                    'explanation' => 'Partition tolerance cannot be sacrificed in a real distributed system, since network partitions will happen. When one occurs, the system must trade off between returning consistent data (which may require blocking) and remaining available (which may return stale data).',
                    'options' => [
                        ['text' => 'Consistency and Availability', 'isCorrect' => true],
                        ['text' => 'Consistency and Partition Tolerance', 'isCorrect' => false],
                        ['text' => 'Availability and Partition Tolerance', 'isCorrect' => false],
                        ['text' => 'None — all three can always be guaranteed', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'What guarantee does an eventually consistent system actually provide?',
                    'explanation' => 'Eventual consistency only promises convergence once writes stop — it makes no promise about how long that takes or what a read returns in the meantime.',
                    'options' => [
                        ['text' => 'Every read immediately returns the most recent write', 'isCorrect' => false],
                        ['text' => 'If no new updates are made, all replicas will eventually converge to the same value', 'isCorrect' => true],
                        ['text' => 'Writes are rejected outright during any partition', 'isCorrect' => false],
                        ['text' => 'Consistency is guaranteed, but only for the client that performed the write', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'Which state does a circuit breaker enter to stop sending requests to a failing downstream service?',
                    'explanation' => 'Open short-circuits calls immediately instead of waiting on a doomed request. After a cooldown period the breaker moves to half-open to test whether the downstream service has recovered.',
                    'options' => [
                        ['text' => 'Closed', 'isCorrect' => false],
                        ['text' => 'Half-open', 'isCorrect' => false],
                        ['text' => 'Open', 'isCorrect' => true],
                        ['text' => 'Paused', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'How does the Saga pattern keep data consistent across services without a two-phase commit?',
                    'explanation' => 'Each step commits its own local transaction independently; if a later step fails, previously completed steps are undone via explicit compensating actions rather than a distributed lock.',
                    'options' => [
                        ['text' => 'By locking every resource involved until the whole transaction completes', 'isCorrect' => false],
                        ['text' => 'By running a sequence of local transactions, with compensating actions to undo prior steps if a later step fails', 'isCorrect' => true],
                        ['text' => 'By retrying failed steps forever without ever rolling back', 'isCorrect' => false],
                        ['text' => 'By requiring every service to share one physical database', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'What problem does the Outbox pattern solve?',
                    'explanation' => 'The outgoing event is written to an outbox table in the same local transaction as the business change, then a separate relay process publishes it — avoiding the dual-write problem without needing two-phase commit.',
                    'options' => [
                        ['text' => 'It replaces the need for a message broker entirely', 'isCorrect' => false],
                        ['text' => 'It guarantees exactly-once delivery with no deduplication needed downstream', 'isCorrect' => false],
                        ['text' => 'It guarantees at-least-once event delivery by writing the event in the same database transaction as the business data change', 'isCorrect' => true],
                        ['text' => 'It prevents duplicate messages purely through network-level retries', 'isCorrect' => false],
                    ],
                ],
            ],
        ],
        'domain-driven-design' => [
            'title' => 'Domain-Driven Design: Check Your Understanding',
            'intro' => 'Six questions covering strategic design, aggregates, value objects, CQRS, event sourcing, and the repository pattern from this learning path.',
            'questions' => [
                [
                    'text' => "What is a 'Bounded Context' in Domain-Driven Design?",
                    'explanation' => 'A Bounded Context defines where a specific model and its ubiquitous language are valid — the same term (e.g. "Order") can mean something different in another context.',
                    'options' => [
                        ['text' => "A microservice's Docker container", 'isCorrect' => false],
                        ['text' => 'A shared kernel used identically by every subdomain', 'isCorrect' => false],
                        ['text' => 'A physical database boundary enforced by a DBA', 'isCorrect' => false],
                        ['text' => 'An explicit boundary within which a particular domain model applies with a consistent, unambiguous meaning', 'isCorrect' => true],
                    ],
                ],
                [
                    'text' => 'What is the primary responsibility of an Aggregate Root?',
                    'explanation' => 'External code only ever references the aggregate root, which is responsible for keeping the whole cluster of objects inside it consistent.',
                    'options' => [
                        ['text' => "To expose every internal entity's setters directly, for flexibility", 'isCorrect' => false],
                        ['text' => "To act as the single entry point that enforces invariants across the aggregate's internal entities", 'isCorrect' => true],
                        ['text' => "To store the aggregate's data spread across multiple databases for scalability", 'isCorrect' => false],
                        ['text' => 'To eliminate the need for a repository', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'Which characteristic distinguishes a Value Object from an Entity in DDD?',
                    'explanation' => 'Two Value Objects with the same attribute values are considered equal and interchangeable — unlike an Entity, which retains a distinct identity even if its attributes change.',
                    'options' => [
                        ['text' => 'A Value Object has a unique identity that is tracked over its lifetime', 'isCorrect' => false],
                        ['text' => 'A Value Object must always be persisted in its own dedicated table', 'isCorrect' => false],
                        ['text' => 'A Value Object is defined entirely by its attributes, is immutable, and is interchangeable with another instance holding equal attributes', 'isCorrect' => true],
                        ['text' => 'A Value Object can only ever wrap a single primitive type', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'What does CQRS (Command Query Responsibility Segregation) separate?',
                    'explanation' => 'CQRS lets the write side optimize for enforcing business rules and the read side optimize for query shape/performance, instead of forcing one model to serve both purposes.',
                    'options' => [
                        ['text' => 'Synchronous APIs from asynchronous APIs', 'isCorrect' => false],
                        ['text' => 'The model used for writes (commands) from the model used for reads (queries)', 'isCorrect' => true],
                        ['text' => 'Unit tests from integration tests', 'isCorrect' => false],
                        ['text' => 'Frontend code from backend code', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => "In Event Sourcing, how is an entity's current state determined?",
                    'explanation' => 'The event log is the source of truth; current state is a projection obtained by folding all events in order (snapshots are an optional optimization, not a replacement for the log).',
                    'options' => [
                        ['text' => "It's derived by replaying the full sequence of stored events for that entity", 'isCorrect' => true],
                        ['text' => "It's read directly from a single row that gets overwritten in place", 'isCorrect' => false],
                        ['text' => "It's cached once and never recalculated again", 'isCorrect' => false],
                        ['text' => "It's taken from the most recent snapshot only, with all prior events discarded", 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'What is the main purpose of the Repository pattern?',
                    'explanation' => 'A repository lets domain code work with aggregates as if they were an in-memory collection, keeping persistence concerns out of the domain model.',
                    'options' => [
                        ['text' => 'To replace the need for an ORM entirely', 'isCorrect' => false],
                        ['text' => 'To handle HTTP routing for domain entities', 'isCorrect' => false],
                        ['text' => 'To mediate between the domain model and the data mapping layer, exposing a collection-like interface for retrieving and persisting aggregates', 'isCorrect' => true],
                        ['text' => 'To expose raw SQL queries directly to the domain layer', 'isCorrect' => false],
                    ],
                ],
            ],
        ],
        'architecture-patterns' => [
            'title' => 'Architecture Patterns: Check Your Understanding',
            'intro' => 'Five questions covering hexagonal and clean architecture, microservices trade-offs, and the API gateway pattern from this learning path.',
            'questions' => [
                [
                    'text' => "In Hexagonal Architecture (Ports and Adapters), what is the role of a 'port'?",
                    'explanation' => 'Ports are interfaces owned by the application core; adapters (HTTP controllers, database repositories, message consumers, ...) implement or call them, keeping the core decoupled from any specific technology.',
                    'options' => [
                        ['text' => 'A network port number the service listens on', 'isCorrect' => false],
                        ['text' => 'A database connection pool', 'isCorrect' => false],
                        ['text' => 'An interface defined by the application core that adapters implement to connect it to the outside world', 'isCorrect' => true],
                        ['text' => 'A physical slot in a server rack', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'What does the Dependency Rule in Clean Architecture state?',
                    'explanation' => 'Inner layers (business rules) must know nothing about outer layers (frameworks, UI, database) — dependencies always point toward the center, never outward.',
                    'options' => [
                        ['text' => 'Every class must depend on at least one interface', 'isCorrect' => false],
                        ['text' => 'Outer layers may never be covered by tests', 'isCorrect' => false],
                        ['text' => 'All dependencies must be resolved through a global service locator', 'isCorrect' => false],
                        ['text' => 'Source code dependencies must point only inward, toward higher-level policies', 'isCorrect' => true],
                    ],
                ],
                [
                    'text' => 'Which of these is a commonly cited drawback of a microservices architecture?',
                    'explanation' => 'Splitting a system into independently deployable services trades a simpler deployment model for the operational overhead of distributed tracing, network failure handling, and cross-service versioning.',
                    'options' => [
                        ['text' => 'Inability to scale individual components independently', 'isCorrect' => false],
                        ['text' => 'Being forced onto a single shared database for all services', 'isCorrect' => false],
                        ['text' => 'Increased operational complexity from distributed communication, deployment, and monitoring', 'isCorrect' => true],
                        ['text' => 'Elimination of network latency between components', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'What is a reasonable signal that part of a monolith should be split into a separate service?',
                    'explanation' => 'Splitting is justified by a concrete constraint — e.g. one module needs independent scaling or a separate release cadence — not by novelty for its own sake.',
                    'options' => [
                        ['text' => 'The team wants to try a trendy new technology', 'isCorrect' => false],
                        ['text' => 'A specific module has distinct scaling, deployment, or ownership needs that the monolith is constraining', 'isCorrect' => true],
                        ['text' => 'The codebase is under 1,000 lines of code', 'isCorrect' => false],
                        ['text' => 'All modules already deploy together on the same schedule with no friction', 'isCorrect' => false],
                    ],
                ],
                [
                    'text' => 'What is a primary responsibility of an API Gateway in a service-oriented architecture?',
                    'explanation' => 'The gateway sits in front of backing services, giving clients one endpoint while centralizing concerns (auth, rate limiting, aggregation) that would otherwise be duplicated in every service.',
                    'options' => [
                        ['text' => 'Storing the business data owned by every backing service', 'isCorrect' => false],
                        ['text' => 'Compiling application code at build time', 'isCorrect' => false],
                        ['text' => 'Eliminating the need for services to communicate with each other', 'isCorrect' => false],
                        ['text' => 'Acting as a single entry point that routes requests and can centralize cross-cutting concerns like auth, rate limiting, and response aggregation', 'isCorrect' => true],
                    ],
                ],
            ],
        ],
    ];

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em,
    ) {
        $this->messageBus = $messageBus;
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function getDependencies(): array
    {
        return [ArticleFixture::class];
    }

    /** @throws DBALException */
    public function load(ObjectManager $manager): void
    {
        $homepageUuid = $this->connection->fetchOne(
            'SELECT resource_id FROM ro_routes WHERE slug = ?',
            ['/'],
        );

        if (!\is_string($homepageUuid) || '' === $homepageUuid) {
            return;
        }

        foreach (self::EXERCISES as $slug => $exercise) {
            $this->upsertExercise($homepageUuid, $slug, $exercise);
        }
    }

    /**
     * @param array{title: string, intro: string, questions: list<array{text: string, explanation: string|null, options: list<array{text: string, isCorrect: bool}>}>} $exercise
     *
     * @throws DBALException
     */
    private function upsertExercise(string $homepageUuid, string $slug, array $exercise): void
    {
        $url = '/learning-paths/' . $slug . '/exercise';
        $questionSet = $this->upsertQuestionSet($exercise['title'], $exercise['questions']);

        $data = [
            'locale' => 'en',
            'template' => 'exercise',
            'title' => $exercise['title'],
            'url' => $url,
            'intro' => $exercise['intro'],
            'questionSet' => $questionSet->getId(),
        ];

        $existingUuid = $this->connection->fetchOne(
            'SELECT resource_id FROM ro_routes WHERE slug = ?',
            [$url],
        );
        $existingUuid = \is_string($existingUuid) ? $existingUuid : null;

        if (null !== $existingUuid) {
            $this->handle(new Envelope(
                new ModifyPageMessage(['uuid' => $existingUuid], $data),
                [new EnableFlushStamp()],
            ));
        } else {
            /** @var PageInterface $page */
            $page = $this->handle(new Envelope(
                new CreatePageMessage('architecture-hub', $homepageUuid, $data),
                [new EnableFlushStamp()],
            ));
            $existingUuid = $page->getUuid();
        }

        $this->handle(new Envelope(
            new ApplyWorkflowTransitionPageMessage(
                ['uuid' => $existingUuid],
                'en',
                WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            ),
            [new EnableFlushStamp()],
        ));
    }

    /**
     * Upserts by title (this project's convention elsewhere is upsert-by-route-slug;
     * QuestionSet has no route, so title is the natural fixture-rerun key here).
     *
     * @param list<array{text: string, explanation: string|null, options: list<array{text: string, isCorrect: bool}>}> $questions
     */
    private function upsertQuestionSet(string $title, array $questions): QuestionSet
    {
        $questionSetTitle = $title . ' Quiz';

        $questionSet = $this->em->getRepository(QuestionSet::class)->findOneBy(['title' => $questionSetTitle]);
        if (null === $questionSet) {
            $questionSet = new QuestionSet($questionSetTitle);
        } else {
            // Re-running this fixture (`--append`) must not leak the previous
            // run's Question/Option rows — this fixture never shares a
            // Question across multiple sets, so removing the old ones
            // outright before rebuilding is safe here. orphanRemoval on
            // Question::$options cascades this to their Options too.
            foreach ($questionSet->getOrderedQuestions() as $oldQuestion) {
                $this->em->remove($oldQuestion);
            }
        }

        $builtQuestions = [];
        foreach ($questions as $question) {
            $entity = new Question($question['text'], $question['explanation']);
            foreach ($question['options'] as $option) {
                $entity->addOption($option['text'], $option['isCorrect']);
            }
            $this->em->persist($entity);
            $builtQuestions[] = $entity;
        }

        $questionSet->replaceQuestions($builtQuestions);
        $this->em->persist($questionSet);
        $this->em->flush();

        return $questionSet;
    }
}
