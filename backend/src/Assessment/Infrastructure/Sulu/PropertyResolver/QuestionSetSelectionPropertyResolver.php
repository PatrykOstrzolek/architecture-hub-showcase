<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\PropertyResolver;

use App\Assessment\Domain\Model\Option;
use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Cache\QuestionSetCacheKey;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContentTypeResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Resolves the `single_question_set_selection` bridge content type (a page-
 * template property storing just a QuestionSet id) into safe, public data
 * for the public headless JSON (`.json` routes, served by
 * HeadlessWebsiteController). Implements sulu/headless-bundle's own
 * ContentTypeResolverInterface — a *separate* resolution system from
 * sulu/sulu's native Sulu\Content\Application\PropertyResolver, which
 * governs a different pipeline (admin form / non-headless content
 * resolution) that HeadlessWebsiteController does not consult. See
 * ADR-0014.
 *
 * Deliberately never includes Option::isCorrect or Question::explanation —
 * this is what makes the old ExerciseAnswerRedactionSubscriber unnecessary:
 * there's nothing to redact because the answer key never enters the
 * response in the first place.
 */
final readonly class QuestionSetSelectionPropertyResolver implements ContentTypeResolverInterface
{
    /**
     * Correctness backstop only, not the primary invalidation mechanism —
     * QuestionSetController/QuestionController explicitly delete the cache
     * entry on save/remove (see Requirement 5, "never serve stale content").
     */
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private QuestionSetRepositoryInterface $questionSets,
        private CacheInterface $cache,
    ) {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (!\is_int($data)) {
            return new ContentView(null, ['id' => null]);
        }

        /** @var array<string, mixed>|null $content */
        $content = $this->cache->get(
            QuestionSetCacheKey::for($data),
            function (ItemInterface $item) use ($data): ?array {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                return $this->toContent($this->questionSets->findWithQuestions($data));
            },
        );

        if (null === $content) {
            return new ContentView(null, ['id' => $data]);
        }

        return new ContentView($content, ['id' => $data]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toContent(?QuestionSet $questionSet): ?array
    {
        if (null === $questionSet) {
            return null;
        }

        return [
            'id' => $questionSet->getId(),
            'title' => $questionSet->getTitle(),
            'questions' => \array_map(
                static fn (Question $question): array => [
                    'id' => $question->getId(),
                    'text' => $question->getText(),
                    'options' => \array_map(
                        static fn (Option $option): array => [
                            'id' => $option->getId(),
                            'text' => $option->getText(),
                        ],
                        $question->getOptions(),
                    ),
                ],
                $questionSet->getOrderedQuestions(),
            ),
        ];
    }

    public static function getContentType(): string
    {
        return 'single_question_set_selection';
    }
}
