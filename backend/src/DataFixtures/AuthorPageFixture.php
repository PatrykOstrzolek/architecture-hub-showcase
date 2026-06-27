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
 * Creates (or updates):
 *   - Author profile pages (`author` template) under /authors/{slug}
 *   - The /authors listing page (`authors` template) that curates them via page_selection
 * Pages are not Articles, so they never appear in /api/articles listings.
 * Run with: php -d memory_limit=1G bin/console doctrine:fixtures:load --append --group=dev
 */
class AuthorPageFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    use HandleTrait;

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

        if (!$homepageUuid) {
            // sulu:build dev runs fixtures before the homepage exists — skip silently.
            // Re-run: doctrine:fixtures:load --append --group=dev after the build completes.
            return;
        }

        $this->addPageToNavigation($homepageUuid);

        $this->upsertAuthorPage(
            $homepageUuid,
            'Jane Kowalski',
            '/authors/jane-kowalski',
            'Backend Engineer',
            'Jane specialises in event-driven architecture and high-throughput data pipelines. '
            . 'She contributes to open-source tooling and writes about domain modelling and clean service boundaries.',
        );

        $this->upsertAuthorsListingPage($homepageUuid);
    }

    /** @throws DBALException */
    private function addPageToNavigation(string $pageUuid, string $context = 'main'): void
    {
        $dimContentId = $this->connection->fetchOne(
            'SELECT id FROM pa_page_dimension_contents WHERE pageuuid = ? AND stage = ? AND locale = ?',
            [$pageUuid, 'live', 'en'],
        );

        if (!is_int($dimContentId) && !is_string($dimContentId)) {
            return;
        }

        $exists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM pa_page_dimension_content_navigation_contexts WHERE page_dimension_content_id = ? AND name = ?',
            [$dimContentId, $context],
        );

        if (!$exists) {
            $this->connection->executeStatement(
                'INSERT INTO pa_page_dimension_content_navigation_contexts (name, page_dimension_content_id) VALUES (?, ?)',
                [$context, $dimContentId],
            );
        }
    }

    /** @throws DBALException */
    private function upsertAuthorsListingPage(string $homepageUuid): void
    {
        $authorUuids = $this->connection->fetchFirstColumn(
            'SELECT resource_id FROM ro_routes WHERE slug LIKE ? AND resource_key = ?',
            ['/authors/%', 'pages'],
        );

        $data = [
            'locale'             => 'en',
            'template'           => 'authors',
            'title'              => 'Authors',
            'url'                => '/authors',
            'authors'            => $authorUuids,
            'navigationContexts' => ['main'],
        ];

        $existingUuid = $this->connection->fetchOne(
            'SELECT resource_id FROM ro_routes WHERE slug = ?',
            ['/authors'],
        );
        $existingUuid = is_string($existingUuid) ? $existingUuid : null;

        if ($existingUuid !== null) {
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

    /** @throws DBALException */
    private function upsertAuthorPage(
        string $homepageUuid,
        string $title,
        string $url,
        string $position,
        string $bio,
    ): void {
        [$firstName, $lastName] = explode(' ', $title, 2);

        $contactId = $this->connection->fetchOne(
            'SELECT id FROM co_contacts WHERE firstname = ? AND lastname = ?',
            [$firstName, $lastName],
        );

        $articles = $contactId
            ? $this->connection->fetchFirstColumn(
                'SELECT DISTINCT articleuuid FROM ar_article_dimension_contents WHERE stage = ? AND author_id = ?',
                ['live', $contactId],
            )
            : [];

        $data = [
            'locale'    => 'en',
            'template'  => 'author',
            'title'     => $title,
            'url'       => $url,
            'position'  => $position,
            'bio'       => $bio,
            'articles'  => $articles,
        ];

        $existingUuid = $this->connection->fetchOne(
            'SELECT resource_id FROM ro_routes WHERE slug = ?',
            [$url],
        );
        $existingUuid = is_string($existingUuid) ? $existingUuid : null;

        if ($existingUuid !== null) {
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
