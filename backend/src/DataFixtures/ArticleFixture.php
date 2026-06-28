<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\Persistence\ObjectManager;
use Sulu\Article\Application\Message\ApplyWorkflowTransitionArticleMessage;
use Sulu\Article\Application\Message\CreateArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\CategoryBundle\Entity\Category as CategoryEntity;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\TagBundle\Entity\Tag as TagEntity;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Publishes 20 articles from data/articles.csv + data/blocks.csv.
 * Depends on: TagFixture, CategoryFixture, ContactFixture.
 * Run with: php -d memory_limit=1G bin/console doctrine:fixtures:load --group=dev.
 */
class ArticleFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
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
        return [TagFixture::class, CategoryFixture::class, ContactFixture::class];
    }

    /** @throws DBALException|\RuntimeException */
    public function load(ObjectManager $manager): void
    {
        $parentUuid = $this->connection->fetchOne(
            'SELECT resource_id FROM ro_routes WHERE slug = ?',
            ['/'],
        );
        if (!\is_string($parentUuid) || '' === $parentUuid) {
            // sulu:build dev runs fixtures before creating the homepage — skip silently here.
            // After the build completes, run: doctrine:fixtures:load --append --group=dev
            return;
        }

        /** @var Contact $jane */
        $jane = $this->getReference('contact-jane', Contact::class);

        $rawAdamId = $this->connection->fetchOne(
            "SELECT id FROM co_contacts WHERE firstname = 'Adam' AND lastname = 'Ministrator' LIMIT 1",
        );
        if (!\is_numeric($rawAdamId) || 0 === (int) $rawAdamId) {
            throw new \RuntimeException('Adam Ministrator contact not found. Run sulu:build dev first.');
        }
        $adamId = (int) $rawAdamId;

        $authorMap = ['adam' => $adamId, 'jane' => $jane->getId()];

        $blocks = $this->loadBlocks();

        foreach ($this->loadArticles($authorMap, $blocks) as $def) {
            if ($this->routeExists(\is_string($def['slug']) ? $def['slug'] : '')) {
                continue;
            }
            $this->createArticle($manager, $def, $parentUuid);
        }
    }

    // ── CSV loaders ───────────────────────────────────────────────────────────

    /**
     * @param array<string, int> $authorMap
     * @param array<string, list<array<string, mixed>>> $blocks
     *
     * @throws \RuntimeException
     *
     * @return list<array<string, mixed>>
     */
    private function loadArticles(array $authorMap, array $blocks): array
    {
        $rows = $this->readCsv(__DIR__ . '/data/articles.csv');
        $articles = [];
        foreach ($rows as $row) {
            $articles[] = [
                'slug' => $row['slug'],
                'authored' => $row['authored'],
                'author' => $authorMap[$row['author']],
                'categories' => \array_map(
                    fn (string $k) => $this->categoryId($k),
                    \array_filter(\explode('|', $row['categories'])),
                ),
                'tags' => \array_map(
                    fn (string $k) => $this->tagId($k),
                    \array_filter(\explode('|', $row['tags'])),
                ),
                'title' => $row['title'],
                'summary' => $row['summary'],
                'body' => $blocks[$row['slug']] ?? [],
            ];
        }

        return $articles;
    }

    /**
     * @throws \RuntimeException
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadBlocks(): array
    {
        $rows = $this->readCsv(__DIR__ . '/data/blocks.csv');
        $bySlug = [];
        foreach ($rows as $row) {
            $extra = match ($row['type']) {
                'text' => ['text' => $row['text']],
                'callout' => ['style' => $row['style'], 'content' => $row['content']],
                'code' => ['language' => $row['language'], 'caption' => $row['caption'], 'code' => $row['code']],
                default => throw new \RuntimeException("Unknown block type: {$row['type']}"),
            };
            $bySlug[$row['article']][] = ['_id' => $row['block_id'], 'type' => $row['type'], ...$extra];
        }

        return $bySlug;
    }

    /**
     * @throws \RuntimeException
     *
     * @return array<array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = \fopen($path, 'r');
        if (false === $handle) {
            throw new \RuntimeException("Cannot open fixture CSV: $path");
        }
        $headers = \fgetcsv($handle);
        if (false === $headers) {
            \fclose($handle);
            throw new \RuntimeException("Empty fixture CSV: $path");
        }
        $stringHeaders = \array_map('strval', $headers);
        $rows = [];
        while (($data = \fgetcsv($handle)) !== false) {
            $rows[] = \array_combine($stringHeaders, \array_map('strval', $data));
        }
        \fclose($handle);

        return $rows;
    }

    // ── Reference helpers ─────────────────────────────────────────────────────

    private function tagId(string $key): int
    {
        /** @var TagEntity $tag */
        $tag = $this->getReference('tag-' . $key, TagEntity::class);

        return $tag->getId();
    }

    private function categoryId(string $key): int
    {
        /** @var CategoryEntity $cat */
        $cat = $this->getReference('category-' . $key, CategoryEntity::class);

        return $cat->getId();
    }

    // ── Article creation ──────────────────────────────────────────────────────

    /** @param array<string, mixed> $def */
    private function createArticle(ObjectManager $manager, array $def, string $parentUuid): void
    {
        /** @var ArticleInterface $article */
        $article = $this->handle(new CreateArticleMessage([
            'locale' => 'en',
            'template' => 'article',
            'mainWebspace' => 'architecture-hub',
            'title' => $def['title'],
            'summary' => $def['summary'],
            'author' => $def['author'],
            'authored' => $def['authored'],
            'categories' => $def['categories'],
            'tags' => $def['tags'],
            'url' => [
                'page' => ['uuid' => $parentUuid, 'path' => '/'],
                'suffix' => $def['slug'],
                'resourceKey' => 'pages',
            ],
            'body' => $def['body'],
        ]));

        $manager->flush();

        $this->handle(new ApplyWorkflowTransitionArticleMessage(
            ['uuid' => $article->getId()],
            'en',
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
        ));

        $manager->flush();
    }

    /** @throws DBALException */
    private function routeExists(string $slug): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM ro_routes WHERE slug = ?',
            ['/' . $slug],
        );
    }
}
