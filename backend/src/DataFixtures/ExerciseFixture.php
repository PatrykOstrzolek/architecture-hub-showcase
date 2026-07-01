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
 * NOTE: the question/answer copy below is placeholder ('TODO: ...'). Replace it
 * with real content before running this fixture against an environment you
 * intend to keep — PHPStan/CS-Fixer only validate structure, not copy.
 *
 * Run with: php -d memory_limit=1G bin/console doctrine:fixtures:load --append --group=dev.
 */
class ExerciseFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    use HandleTrait;

    /** @var array<string, array{title: string, intro: string, questions: list<array<string, string>>}> slug => exercise content */
    private const EXERCISES = [
        'distributed-systems-fundamentals' => [
            'title' => 'TODO: Exercise title',
            'intro' => 'TODO: Optional intro text',
            'questions' => [
                [
                    '_id' => 'q1',
                    'type' => 'multiple_choice',
                    'question' => 'TODO: question text',
                    'option_a' => 'TODO: option A',
                    'option_b' => 'TODO: option B',
                    'option_c' => 'TODO: option C',
                    'option_d' => 'TODO: option D',
                    'correct' => 'a',
                    'explanation' => 'TODO: optional explanation',
                ],
                // TODO: add 3-4 more questions
            ],
        ],
        'domain-driven-design' => [
            'title' => 'TODO: Exercise title',
            'intro' => 'TODO: Optional intro text',
            'questions' => [
                [
                    '_id' => 'q1',
                    'type' => 'multiple_choice',
                    'question' => 'TODO: question text',
                    'option_a' => 'TODO: option A',
                    'option_b' => 'TODO: option B',
                    'option_c' => 'TODO: option C',
                    'option_d' => 'TODO: option D',
                    'correct' => 'a',
                    'explanation' => 'TODO: optional explanation',
                ],
                // TODO: add 3-4 more questions
            ],
        ],
        'architecture-patterns' => [
            'title' => 'TODO: Exercise title',
            'intro' => 'TODO: Optional intro text',
            'questions' => [
                [
                    '_id' => 'q1',
                    'type' => 'multiple_choice',
                    'question' => 'TODO: question text',
                    'option_a' => 'TODO: option A',
                    'option_b' => 'TODO: option B',
                    'option_c' => 'TODO: option C',
                    'option_d' => 'TODO: option D',
                    'correct' => 'a',
                    'explanation' => 'TODO: optional explanation',
                ],
                // TODO: add 3-4 more questions
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
