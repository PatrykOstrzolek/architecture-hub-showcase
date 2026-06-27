# Content Fixtures

How to create Doctrine fixtures that publish content through the Sulu 3.x content pipeline.

## Quick start

```bash
# Step 1 — full Sulu initialisation (tags/categories/contacts seeded; articles skip — homepage not yet created)
php -d memory_limit=1G bin/console sulu:build dev

# Step 2 — load articles (homepage now exists, all references resolve)
php -d memory_limit=1G bin/console doctrine:fixtures:load --append --group=dev
```

`sulu:build dev` runs its internal `fixtures` step before the `homepage` step, so `ArticleFixture` detects the missing homepage and returns early. Step 2 re-runs all dev fixtures; the tag/category/contact fixtures are idempotent, and the article fixture now succeeds.

After loading, re-index SEAL so articles appear in search:

```bash
php -d memory_limit=1G bin/console cmsig:seal:reindex --drop
```

---

## How Sulu 3.x stores content

Sulu 3.x uses a **message bus** pattern instead of direct entity manipulation. Every content operation goes through a message:

| Message | Effect |
|---|---|
| `CreateArticleMessage` | Creates a new article in `draft` stage |
| `ModifyArticleMessage` | Updates an existing article |
| `ApplyWorkflowTransitionArticleMessage` | Changes workflow state (e.g. `publish`) |

Under the hood, `CreateArticleMessage` → `ContentPersisterInterface` → writes two `ar_article_dimension_contents` rows (locale-agnostic draft + locale-specific draft). `publish` transition adds a third `live` row that the headless API reads.

**Do not** write to `ar_articles` / `ar_article_dimension_contents` directly — the content pipeline manages denormalisation, route registration, and SEAL indexing automatically.

---

## Dev fixture set

The `--group=dev` fixtures live in `backend/src/DataFixtures/`:

| Class | What it creates | Idempotent? |
|---|---|---|
| `TagFixture` | 26 tags | ✅ `findByName() ?? save()` |
| `CategoryFixture` | 5 categories | ✅ `findOneBy(['key' => ...])` check |
| `ContactFixture` | Jane Kowalski (second author) | ✅ `findOneBy` check |
| `ArticleFixture` | 20 published articles from CSV | ✅ skips existing slugs; skips if homepage missing |

`ArticleFixture` declares `DependentFixtureInterface` → tag/category/contact fixtures always run first and register their Doctrine references before articles load.

### Data files

Article metadata and block content live in CSV files under `backend/src/DataFixtures/data/`:

- **`articles.csv`** — columns: `slug, authored, author, categories, tags, title, summary`
  - `author`: `adam` or `jane` (mapped to contact IDs at load time)
  - `categories` / `tags`: pipe-separated reference keys, e.g. `cap-theorem|consistency|availability`
- **`blocks.csv`** — columns: `article, block_id, type, text, style, content, language, caption, code`
  - `type` values: `text`, `callout`, `code`
  - Multi-line code blocks use standard CSV quoting (`fgetcsv` handles them natively)

Adam's contact ID is resolved at runtime (`SELECT id FROM co_contacts WHERE firstname = 'Adam' AND lastname = 'Ministrator'`) because `sulu:build dev` creates contacts in non-deterministic order.

### Reference system note

Doctrine Fixtures Bundle stores references keyed by **concrete class**, not interface. Use the concrete entity class in both `addReference` and `getReference`:

```php
// ✅ correct — concrete class
$this->getReference('tag-cap-theorem', Tag::class);
$this->getReference('category-ddd', Category::class);

// ❌ wrong — interface key never matches stored reference
$this->getReference('tag-cap-theorem', TagInterface::class);
```

---

## Fixture class skeleton

```php
<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;
use Sulu\Article\Application\Message\ApplyWorkflowTransitionArticleMessage;
use Sulu\Article\Application\Message\CreateArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class MyArticleFixture extends Fixture implements FixtureGroupInterface
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

    /** @throws DBALException|RuntimeException */
    public function load(ObjectManager $manager): void
    {
        $parentPageUuid = $this->connection->fetchOne(
            'SELECT resource_id FROM ro_routes WHERE slug = ?',
            ['/'],
        );
        if (!$parentPageUuid) {
            // sulu:build dev runs fixtures before creating the homepage — skip silently.
            // Run: doctrine:fixtures:load --append --group=dev after the build completes.
            return;
        }

        /** @var ArticleInterface $article */
        $article = $this->handle(new CreateArticleMessage([
            'locale'       => 'en',
            'template'     => 'article',
            'mainWebspace' => 'architecture-hub',
            'title'        => 'My Article Title',
            'summary'      => 'One-sentence lead shown in listings.',
            'author'       => 2,          // co_contacts.id
            'authored'     => '2026-01-15T10:00:00+00:00',
            'categories'   => [1],        // ca_categories.id[]
            'tags'         => [3, 5],     // ta_tags.id[]
            'url'          => [
                'page'        => ['uuid' => $parentPageUuid, 'path' => '/'],
                'suffix'      => 'my-article-slug',
                'resourceKey' => 'pages',
            ],
            'body' => [
                ['_id' => 'a1b2c3d4', 'type' => 'text', 'text' => '<p>Body HTML.</p>'],
            ],
        ]));

        $manager->flush();   // persist draft before publish transition

        $this->handle(new ApplyWorkflowTransitionArticleMessage(
            ['uuid' => $article->getId()],
            'en',
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
        ));

        $manager->flush();   // persist live dimension
    }
}
```

## Block types

```php
// text block
['_id' => 'a1b2c3d4', 'type' => 'text', 'text' => '<p>HTML</p>']

// code block
['_id' => 'b2c3d4e5', 'type' => 'code', 'language' => 'php', 'caption' => 'Optional', 'code' => '<?php echo "hi";']

// callout block
['_id' => 'd4e5f6a7', 'type' => 'callout', 'style' => 'info', 'content' => '<p>Note text.</p>']
// style values: info | tip | warning
```

`_id` must be a stable 8-character hex string, unique within the article.

## Key constraints

- **`mainWebspace`** must match your webspace key (`architecture-hub`). Missing this → article won't appear on the website.
- **`url.suffix`** becomes the route slug. Must be unique across all routes.
- **Two flushes required**: one after `CreateArticleMessage` (persists the draft), one after `ApplyWorkflowTransitionArticleMessage` (persists the live dimension).
- **`MessageBusInterface`** resolves to `sulu_message_bus` (the configured default bus).
- **Idempotency**: check `ro_routes` by slug before creating. Re-running without a guard fails with a unique constraint violation.

## Lookup cheatsheet

```bash
# contacts (authors)
php bin/console doctrine:query:sql "SELECT id, firstname, lastname FROM co_contacts"

# tags
php bin/console doctrine:query:sql "SELECT id, name FROM ta_tags"

# categories
php bin/console doctrine:query:sql \
  "SELECT c.id, ct.translation FROM ca_categories c \
   JOIN ca_category_translations ct ON ct.idcategories = c.id \
   WHERE ct.locale = 'en'"

# parent page UUID for articles at root level
php bin/console doctrine:query:sql "SELECT resource_id FROM ro_routes WHERE slug = '/'"

# existing article routes (to avoid slug collisions)
php bin/console doctrine:query:sql "SELECT slug FROM ro_routes WHERE resource_key = 'articles'"
```
