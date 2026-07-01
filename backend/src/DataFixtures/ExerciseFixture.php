<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
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
 * /learning-paths/{slug}/exercise. LearningPathFixture looks these up by
 * convention slug and links them via its `exercise` field — see ADR 0011.
 *
 * Run with: php -d memory_limit=1G bin/console doctrine:fixtures:load --append --group=dev.
 */
class ExerciseFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    use HandleTrait;

    /** @var array<string, array{title: string, intro: string, questions: list<array<string, string>>}> slug => exercise content */
    private const EXERCISES = [
        'distributed-systems-fundamentals' => [
            'title' => 'Distributed Systems Fundamentals: Check Your Understanding',
            'intro' => 'Five questions covering the CAP theorem, consistency models, and the resilience and messaging patterns from this learning path.',
            'questions' => [
                [
                    '_id' => 'q1',
                    'type' => 'multiple_choice',
                    'question' => 'During a network partition, the CAP theorem forces a distributed system to choose between which two guarantees?',
                    'option_a' => 'Consistency and Availability',
                    'option_b' => 'Consistency and Partition Tolerance',
                    'option_c' => 'Availability and Partition Tolerance',
                    'option_d' => 'None — all three can always be guaranteed',
                    'correct' => 'a',
                    'explanation' => 'Partition tolerance cannot be sacrificed in a real distributed system, since network partitions will happen. When one occurs, the system must trade off between returning consistent data (which may require blocking) and remaining available (which may return stale data).',
                ],
                [
                    '_id' => 'q2',
                    'type' => 'multiple_choice',
                    'question' => 'What guarantee does an eventually consistent system actually provide?',
                    'option_a' => 'Every read immediately returns the most recent write',
                    'option_b' => 'If no new updates are made, all replicas will eventually converge to the same value',
                    'option_c' => 'Writes are rejected outright during any partition',
                    'option_d' => 'Consistency is guaranteed, but only for the client that performed the write',
                    'correct' => 'b',
                    'explanation' => 'Eventual consistency only promises convergence once writes stop — it makes no promise about how long that takes or what a read returns in the meantime.',
                ],
                [
                    '_id' => 'q3',
                    'type' => 'multiple_choice',
                    'question' => 'Which state does a circuit breaker enter to stop sending requests to a failing downstream service?',
                    'option_a' => 'Closed',
                    'option_b' => 'Half-open',
                    'option_c' => 'Open',
                    'option_d' => 'Paused',
                    'correct' => 'c',
                    'explanation' => 'Open short-circuits calls immediately instead of waiting on a doomed request. After a cooldown period the breaker moves to half-open to test whether the downstream service has recovered.',
                ],
                [
                    '_id' => 'q4',
                    'type' => 'multiple_choice',
                    'question' => 'How does the Saga pattern keep data consistent across services without a two-phase commit?',
                    'option_a' => 'By locking every resource involved until the whole transaction completes',
                    'option_b' => 'By running a sequence of local transactions, with compensating actions to undo prior steps if a later step fails',
                    'option_c' => 'By retrying failed steps forever without ever rolling back',
                    'option_d' => 'By requiring every service to share one physical database',
                    'correct' => 'b',
                    'explanation' => 'Each step commits its own local transaction independently; if a later step fails, previously completed steps are undone via explicit compensating actions rather than a distributed lock.',
                ],
                [
                    '_id' => 'q5',
                    'type' => 'multiple_choice',
                    'question' => 'What problem does the Outbox pattern solve?',
                    'option_a' => 'It replaces the need for a message broker entirely',
                    'option_b' => 'It guarantees exactly-once delivery with no deduplication needed downstream',
                    'option_c' => 'It guarantees at-least-once event delivery by writing the event in the same database transaction as the business data change',
                    'option_d' => 'It prevents duplicate messages purely through network-level retries',
                    'correct' => 'c',
                    'explanation' => 'The outgoing event is written to an outbox table in the same local transaction as the business change, then a separate relay process publishes it — avoiding the dual-write problem without needing two-phase commit.',
                ],
            ],
        ],
        'domain-driven-design' => [
            'title' => 'Domain-Driven Design: Check Your Understanding',
            'intro' => 'Six questions covering strategic design, aggregates, value objects, CQRS, event sourcing, and the repository pattern from this learning path.',
            'questions' => [
                [
                    '_id' => 'q1',
                    'type' => 'multiple_choice',
                    'question' => "What is a 'Bounded Context' in Domain-Driven Design?",
                    'option_a' => "A microservice's Docker container",
                    'option_b' => 'A shared kernel used identically by every subdomain',
                    'option_c' => 'A physical database boundary enforced by a DBA',
                    'option_d' => 'An explicit boundary within which a particular domain model applies with a consistent, unambiguous meaning',
                    'correct' => 'd',
                    'explanation' => 'A Bounded Context defines where a specific model and its ubiquitous language are valid — the same term (e.g. "Order") can mean something different in another context.',
                ],
                [
                    '_id' => 'q2',
                    'type' => 'multiple_choice',
                    'question' => 'What is the primary responsibility of an Aggregate Root?',
                    'option_a' => 'To expose every internal entity\'s setters directly, for flexibility',
                    'option_b' => 'To act as the single entry point that enforces invariants across the aggregate\'s internal entities',
                    'option_c' => 'To store the aggregate\'s data spread across multiple databases for scalability',
                    'option_d' => 'To eliminate the need for a repository',
                    'correct' => 'b',
                    'explanation' => 'External code only ever references the aggregate root, which is responsible for keeping the whole cluster of objects inside it consistent.',
                ],
                [
                    '_id' => 'q3',
                    'type' => 'multiple_choice',
                    'question' => 'Which characteristic distinguishes a Value Object from an Entity in DDD?',
                    'option_a' => 'A Value Object has a unique identity that is tracked over its lifetime',
                    'option_b' => 'A Value Object must always be persisted in its own dedicated table',
                    'option_c' => 'A Value Object is defined entirely by its attributes, is immutable, and is interchangeable with another instance holding equal attributes',
                    'option_d' => 'A Value Object can only ever wrap a single primitive type',
                    'correct' => 'c',
                    'explanation' => 'Two Value Objects with the same attribute values are considered equal and interchangeable — unlike an Entity, which retains a distinct identity even if its attributes change.',
                ],
                [
                    '_id' => 'q4',
                    'type' => 'multiple_choice',
                    'question' => 'What does CQRS (Command Query Responsibility Segregation) separate?',
                    'option_a' => 'Synchronous APIs from asynchronous APIs',
                    'option_b' => 'The model used for writes (commands) from the model used for reads (queries)',
                    'option_c' => 'Unit tests from integration tests',
                    'option_d' => 'Frontend code from backend code',
                    'correct' => 'b',
                    'explanation' => 'CQRS lets the write side optimize for enforcing business rules and the read side optimize for query shape/performance, instead of forcing one model to serve both purposes.',
                ],
                [
                    '_id' => 'q5',
                    'type' => 'multiple_choice',
                    'question' => "In Event Sourcing, how is an entity's current state determined?",
                    'option_a' => "It's derived by replaying the full sequence of stored events for that entity",
                    'option_b' => "It's read directly from a single row that gets overwritten in place",
                    'option_c' => "It's cached once and never recalculated again",
                    'option_d' => "It's taken from the most recent snapshot only, with all prior events discarded",
                    'correct' => 'a',
                    'explanation' => 'The event log is the source of truth; current state is a projection obtained by folding all events in order (snapshots are an optional optimization, not a replacement for the log).',
                ],
                [
                    '_id' => 'q6',
                    'type' => 'multiple_choice',
                    'question' => 'What is the main purpose of the Repository pattern?',
                    'option_a' => 'To replace the need for an ORM entirely',
                    'option_b' => 'To handle HTTP routing for domain entities',
                    'option_c' => 'To mediate between the domain model and the data mapping layer, exposing a collection-like interface for retrieving and persisting aggregates',
                    'option_d' => 'To expose raw SQL queries directly to the domain layer',
                    'correct' => 'c',
                    'explanation' => 'A repository lets domain code work with aggregates as if they were an in-memory collection, keeping persistence concerns out of the domain model.',
                ],
            ],
        ],
        'architecture-patterns' => [
            'title' => 'Architecture Patterns: Check Your Understanding',
            'intro' => 'Five questions covering hexagonal and clean architecture, microservices trade-offs, and the API gateway pattern from this learning path.',
            'questions' => [
                [
                    '_id' => 'q1',
                    'type' => 'multiple_choice',
                    'question' => "In Hexagonal Architecture (Ports and Adapters), what is the role of a 'port'?",
                    'option_a' => 'A network port number the service listens on',
                    'option_b' => 'A database connection pool',
                    'option_c' => 'An interface defined by the application core that adapters implement to connect it to the outside world',
                    'option_d' => 'A physical slot in a server rack',
                    'correct' => 'c',
                    'explanation' => 'Ports are interfaces owned by the core application; adapters (HTTP controllers, database repositories, message consumers, ...) implement or call them, keeping the core decoupled from any specific technology.',
                ],
                [
                    '_id' => 'q2',
                    'type' => 'multiple_choice',
                    'question' => 'What does the Dependency Rule in Clean Architecture state?',
                    'option_a' => 'Every class must depend on at least one interface',
                    'option_b' => 'Outer layers may never be covered by tests',
                    'option_c' => 'All dependencies must be resolved through a global service locator',
                    'option_d' => 'Source code dependencies must point only inward, toward higher-level policies',
                    'correct' => 'd',
                    'explanation' => 'Inner layers (business rules) must know nothing about outer layers (frameworks, UI, database) — dependencies always point toward the center, never outward.',
                ],
                [
                    '_id' => 'q3',
                    'type' => 'multiple_choice',
                    'question' => 'Which of these is a commonly cited drawback of a microservices architecture?',
                    'option_a' => 'Inability to scale individual components independently',
                    'option_b' => 'Being forced onto a single shared database for all services',
                    'option_c' => 'Increased operational complexity from distributed communication, deployment, and monitoring',
                    'option_d' => 'Elimination of network latency between components',
                    'correct' => 'c',
                    'explanation' => 'Splitting a system into independently deployable services trades a simpler deployment model for the operational overhead of distributed tracing, network failure handling, and cross-service versioning.',
                ],
                [
                    '_id' => 'q4',
                    'type' => 'multiple_choice',
                    'question' => 'What is a reasonable signal that part of a monolith should be split into a separate service?',
                    'option_a' => 'The team wants to try a trendy new technology',
                    'option_b' => 'A specific module has distinct scaling, deployment, or ownership needs that the monolith is constraining',
                    'option_c' => 'The codebase is under 1,000 lines of code',
                    'option_d' => 'All modules already deploy together on the same schedule with no friction',
                    'correct' => 'b',
                    'explanation' => 'Splitting is justified by a concrete constraint — e.g. one module needs independent scaling or a separate release cadence — not by novelty for its own sake.',
                ],
                [
                    '_id' => 'q5',
                    'type' => 'multiple_choice',
                    'question' => 'What is a primary responsibility of an API Gateway in a service-oriented architecture?',
                    'option_a' => 'Storing the business data owned by every backing service',
                    'option_b' => 'Compiling application code at build time',
                    'option_c' => 'Eliminating the need for services to communicate with each other',
                    'option_d' => 'Acting as a single entry point that routes requests and can centralize cross-cutting concerns like auth, rate limiting, and response aggregation',
                    'correct' => 'd',
                    'explanation' => 'The gateway sits in front of backing services, giving clients one endpoint while centralizing concerns (auth, rate limiting, aggregation) that would otherwise be duplicated in every service.',
                ],
            ],
        ],
    ];

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly Connection $connection,
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
     * @param array{title: string, intro: string, questions: list<array<string, string>>} $exercise
     *
     * @throws DBALException
     */
    private function upsertExercise(string $homepageUuid, string $slug, array $exercise): void
    {
        $url = '/learning-paths/' . $slug . '/exercise';

        $data = [
            'locale' => 'en',
            'template' => 'exercise',
            'title' => $exercise['title'],
            'url' => $url,
            'intro' => $exercise['intro'],
            'questions' => $exercise['questions'],
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
}
