<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Option;
use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Cache\QuestionSetCacheKey;
use Sulu\Component\Rest\ListBuilder\CollectionRepresentation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Admin CRUD for the "Questions" resource — a plain Symfony controller
 * (not Sulu's AbstractRestController/FOSRestBundle machinery, which nothing
 * else in this project uses either) returning JsonResponse directly,
 * matching this project's existing convention (PagePreviewController,
 * ExerciseAttemptController). Protected by the standing `^/admin` ->
 * ROLE_USER access_control rule, same as every other admin screen.
 */
readonly class QuestionController
{
    use BuildsPaginatedRepresentation;

    public function __construct(
        private QuestionRepositoryInterface $questions,
        private QuestionSetRepositoryInterface $questionSets,
        private CacheInterface $cache,
    ) {
    }

    #[Route('/admin/api/questions', name: 'app.admin_api.questions.list', methods: ['GET'])]
    public function cgetAction(Request $request): JsonResponse
    {
        $ids = IdListQueryParser::parse($request->query->get('ids', ''));
        if ([] !== $ids) {
            $items = \array_map($this->toListItem(...), $this->questions->findByIds($ids));

            return new JsonResponse((new CollectionRepresentation($items, 'questions'))->toArray());
        }

        $representation = $this->buildPaginatedRepresentation(
            $request,
            'questions',
            fn (int $page, int $limit): array => \array_map(
                $this->toListItem(...),
                $this->questions->findPaginated($page, $limit),
            ),
            fn (): int => $this->questions->count(),
        );

        return new JsonResponse($representation->toArray());
    }

    #[Route('/admin/api/questions/{id}', name: 'app.admin_api.questions.get', methods: ['GET'])]
    public function getAction(int $id): JsonResponse
    {
        $question = $this->questions->findWithOptions($id);
        if (null === $question) {
            return new JsonResponse(['error' => 'Question not found.'], 404);
        }

        return new JsonResponse($this->toDetail($question));
    }

    #[Route('/admin/api/questions', name: 'app.admin_api.questions.post', methods: ['POST'])]
    public function postAction(Request $request): JsonResponse
    {
        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid request payload.'], 400);
        }

        $question = new Question($this->stringOrEmpty($data['text'] ?? null), $this->stringOrNull($data['explanation'] ?? null));
        $this->applyOptions($question, $data['options'] ?? []);
        $this->questions->save($question);

        return new JsonResponse($this->toDetail($question));
    }

    #[Route('/admin/api/questions/{id}', name: 'app.admin_api.questions.put', methods: ['PUT'])]
    public function putAction(int $id, Request $request): JsonResponse
    {
        $question = $this->questions->findWithOptions($id);
        if (null === $question) {
            return new JsonResponse(['error' => 'Question not found.'], 404);
        }

        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid request payload.'], 400);
        }

        $question->update(
            \is_string($data['text'] ?? null) ? $data['text'] : $question->getText(),
            $this->stringOrNull($data['explanation'] ?? null),
        );
        $this->applyOptions($question, $data['options'] ?? []);
        $this->questions->save($question);
        $this->invalidateQuestionSetsCache($this->questionSets->findQuestionSetIdsContaining($id));

        return new JsonResponse($this->toDetail($question));
    }

    #[Route('/admin/api/questions/{id}', name: 'app.admin_api.questions.delete', methods: ['DELETE'])]
    public function deleteAction(int $id): Response
    {
        $question = $this->questions->find($id);
        if (null === $question) {
            return new JsonResponse(['error' => 'Question not found.'], 404);
        }

        // Look up affected QuestionSets BEFORE remove(): assessment_question_set_item.question_id
        // is ON DELETE CASCADE, so the join rows findQuestionSetIdsContaining() needs are gone
        // as soon as the DELETE runs — invalidating after remove() would silently do nothing.
        $questionSetIds = $this->questionSets->findQuestionSetIdsContaining($id);
        $this->questions->remove($question);
        $this->invalidateQuestionSetsCache($questionSetIds);

        return new Response(status: 204);
    }

    /**
     * A Question can belong to more than one QuestionSet — every QuestionSet
     * referencing it must have its headless-resolved cache entry dropped,
     * not just the one directly edited (see Requirement 5). Not called from
     * postAction(): a newly-created Question has no QuestionSetItem rows yet.
     *
     * @param list<int> $questionSetIds
     */
    private function invalidateQuestionSetsCache(array $questionSetIds): void
    {
        foreach ($questionSetIds as $questionSetId) {
            $this->cache->delete(QuestionSetCacheKey::for($questionSetId));
        }
    }

    private function applyOptions(Question $question, mixed $rawOptions): void
    {
        $options = \is_array($rawOptions) ? $rawOptions : [];
        $question->replaceOptions(\array_values(\array_map(
            fn (mixed $option): array => [
                'text' => \is_array($option) ? $this->stringOrEmpty($option['text'] ?? null) : '',
                'isCorrect' => \is_array($option) && true === ($option['isCorrect'] ?? false),
            ],
            $options,
        )));
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function stringOrEmpty(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Question $question): array
    {
        return [
            'id' => $question->getId(),
            'text' => $question->getText(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(Question $question): array
    {
        return [
            'id' => $question->getId(),
            'text' => $question->getText(),
            'explanation' => $question->getExplanation(),
            'options' => \array_map(
                static fn (Option $option): array => [
                    // Sulu's generic block field requires each item to carry
                    // the block "type" it renders as, matching question_details.xml's
                    // <type name="default">.
                    'type' => 'default',
                    'id' => $option->getId(),
                    'text' => $option->getText(),
                    'isCorrect' => $option->isCorrect(),
                ],
                $question->getOptions(),
            ),
        ];
    }
}
