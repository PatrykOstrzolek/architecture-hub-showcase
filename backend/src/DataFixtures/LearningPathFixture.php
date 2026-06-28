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
 * Creates (or updates) three learning path pages and the /learning-paths listing page.
 * Run with: php -d memory_limit=1G bin/console doctrine:fixtures:load --append --group=dev.
 */
class LearningPathFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    use HandleTrait;

    /** @var array<string, list<string>> slug => ordered article slugs */
    private const PATHS = [
        'distributed-systems-fundamentals' => [
            '/understanding-the-cap-theorem',
            '/eventual-consistency-in-practice',
            '/circuit-breaker-pattern',
            '/saga-pattern-distributed-transactions',
            '/the-outbox-pattern-for-reliable-messaging',
        ],
        'domain-driven-design' => [
            '/domain-driven-design-strategic-patterns',
            '/domain-driven-design-aggregates',
            '/value-objects-in-ddd',
            '/cqrs-command-query-responsibility-segregation',
            '/event-sourcing-storing-state-as-events',
            '/the-repository-pattern',
        ],
        'architecture-patterns' => [
            '/hexagonal-architecture-ports-and-adapters',
            '/clean-architecture-the-dependency-rule',
            '/microservices-architecture-promises-and-pitfalls',
            '/monolith-vs-microservices-when-to-split',
            '/api-gateway-pattern',
        ],
    ];

    /** @var array<string, string> slug => title */
    private const TITLES = [
        'distributed-systems-fundamentals' => 'Distributed Systems Fundamentals',
        'domain-driven-design' => 'Domain-Driven Design',
        'architecture-patterns' => 'Architecture Patterns',
    ];

    /** @var array<string, string> slug => description */
    private const DESCRIPTIONS = [
        'distributed-systems-fundamentals' => 'Master the core concepts behind reliable distributed systems — from consistency guarantees '
            . 'and failure handling to messaging patterns that keep services in sync.',
        'domain-driven-design' => 'Learn to model complex domains with precision. Covers strategic design, aggregates, '
            . 'value objects, CQRS, event sourcing, and the repository pattern.',
        'architecture-patterns' => 'A tour of the structural patterns that keep large systems maintainable — '
            . 'from hexagonal architecture and clean code to microservices decomposition.',
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

        $lpUuids = [];
        foreach (self::PATHS as $slug => $articleSlugs) {
            $lpUuids[] = $this->upsertLearningPath(
                $homepageUuid,
                $slug,
                self::TITLES[$slug],
                self::DESCRIPTIONS[$slug],
                $articleSlugs,
            );
        }

        $this->upsertListingPage($homepageUuid, $lpUuids);
    }

    /**
     * @param list<string> $articleSlugs
     *
     * @throws DBALException
     */
    private function upsertLearningPath(
        string $homepageUuid,
        string $slug,
        string $title,
        string $description,
        array $articleSlugs,
    ): string {
        $url = '/learning-paths/' . $slug;

        $articleUuids = [];
        foreach ($articleSlugs as $articleSlug) {
            $uuid = $this->connection->fetchOne(
                'SELECT resource_id FROM ro_routes WHERE slug = ? AND resource_key = ?',
                [$articleSlug, 'articles'],
            );
            if (\is_string($uuid)) {
                $articleUuids[] = $uuid;
            }
        }

        $data = [
            'locale' => 'en',
            'template' => 'learning-path',
            'title' => $title,
            'url' => $url,
            'description' => $description,
            'articles' => $articleUuids,
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

        return $existingUuid;
    }

    /**
     * @param list<string> $lpUuids
     *
     * @throws DBALException
     */
    private function upsertListingPage(string $homepageUuid, array $lpUuids): void
    {
        $data = [
            'locale' => 'en',
            'template' => 'learning-paths',
            'title' => 'Learning Paths',
            'url' => '/learning-paths',
            'paths' => $lpUuids,
            'navigationContexts' => ['main'],
        ];

        $existingUuid = $this->connection->fetchOne(
            'SELECT resource_id FROM ro_routes WHERE slug = ?',
            ['/learning-paths'],
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
