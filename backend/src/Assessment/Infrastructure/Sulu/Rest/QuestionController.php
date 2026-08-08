<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Option;
use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Cache\QuestionSetCacheKey;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Sulu\Component\Rest\ListBuilder\CollectionRepresentation;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Admin CRUD for the "Questions" resource. Uses Sulu's documented admin
 * pattern (ViewHandlerInterface + View::create(), per
 * docs.sulu.io/2.x/book/extend-admin.html's EventController example,
 * mirroring Sulu's own TagController) rather than plain JsonResponse — this
 * is admin-only plumbing (protected by the standing `^/admin` -> ROLE_USER
 * access_control rule), unlike the headless-facing controllers
 * (ExerciseAttemptController, PagePreviewController) which stay on
 * JsonResponse since they serialize plain scalars, not Sulu Representation
 * objects, and FOSRestBundle's zone is deliberately scoped to `^/admin/*`
 * in fos_rest.yaml.
 */
readonly class QuestionController
{
    public function __construct(
        private QuestionRepositoryInterface $questions,
        private QuestionSetRepositoryInterface $questionSets,
        private CacheInterface $cache,
        private RestHelperInterface $restHelper,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private ViewHandlerInterface $viewHandler,
    ) {
    }

    #[Route('/admin/api/questions', name: 'app.admin_api.questions.list', methods: ['GET'], format: 'json')]
    public function listAction(Request $request): Response
    {
        $ids = IdListQueryParser::parseAndSanitizeRequest($request);
        ListPageAndLimitSanitizer::sanitize($request);

        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors('questions');
        if (null === $fieldDescriptors) {
            throw new \LogicException('No field descriptors registered for list key "questions" — check config/lists/questions.xml.');
        }

        $listBuilder = $this->listBuilderFactory->create(Question::class);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        if ([] !== $ids) {
            // Matches Sulu's own TagController::cgetAction() (its flat=true branch):
            // initializeListBuilder() already applied the `ids` filter via
            // RestHelper -> ListRestHelper::getIds() -> setIds(), so this only
            // needs to lift the default page-size limit. Verified by hand
            // (see git history) that the client-side MultiSelectionStore.js
            // re-sorts by the requested id order itself, so DoctrineListBuilder
            // not preserving that order here is a non-issue.
            $listBuilder->limit(\count($ids));

            return $this->viewHandler->handle(View::create(new CollectionRepresentation($listBuilder->execute(), 'questions')));
        }

        $representation = new PaginatedRepresentation(
            $listBuilder->execute(),
            'questions',
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->viewHandler->handle(View::create($representation));
    }

    #[Route('/admin/api/questions/{id}', name: 'app.admin_api.questions.get', methods: ['GET'], format: 'json')]
    public function showAction(int $id): Response
    {
        $question = $this->questions->findWithOptions($id);
        if (null === $question) {
            return $this->viewHandler->handle(View::create(['error' => 'Question not found.'], 404));
        }

        return $this->viewHandler->handle(View::create($this->toDetail($question)));
    }

    #[Route('/admin/api/questions', name: 'app.admin_api.questions.post', methods: ['POST'], format: 'json')]
    public function createAction(Request $request): Response
    {
        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return $this->viewHandler->handle(View::create(['error' => 'Invalid request payload.'], 400));
        }

        $question = new Question($this->stringOrEmpty($data['text'] ?? null), $this->stringOrNull($data['explanation'] ?? null));
        $this->applyOptions($question, $data['options'] ?? []);
        $this->questions->save($question);

        return $this->viewHandler->handle(View::create($this->toDetail($question)));
    }

    #[Route('/admin/api/questions/{id}', name: 'app.admin_api.questions.put', methods: ['PUT'], format: 'json')]
    public function updateAction(int $id, Request $request): Response
    {
        $question = $this->questions->findWithOptions($id);
        if (null === $question) {
            return $this->viewHandler->handle(View::create(['error' => 'Question not found.'], 404));
        }

        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return $this->viewHandler->handle(View::create(['error' => 'Invalid request payload.'], 400));
        }

        $question->update(
            \is_string($data['text'] ?? null) ? $data['text'] : $question->getText(),
            $this->stringOrNull($data['explanation'] ?? null),
        );
        $this->applyOptions($question, $data['options'] ?? []);
        $this->questions->save($question);
        $this->invalidateQuestionSetsCache($this->questionSets->findQuestionSetIdsContaining($id));

        return $this->viewHandler->handle(View::create($this->toDetail($question)));
    }

    #[Route('/admin/api/questions/{id}', name: 'app.admin_api.questions.delete', methods: ['DELETE'], format: 'json')]
    public function deleteAction(int $id): Response
    {
        $question = $this->questions->find($id);
        if (null === $question) {
            return $this->viewHandler->handle(View::create(['error' => 'Question not found.'], 404));
        }

        // Look up affected QuestionSets BEFORE remove(): assessment_question_set_item.question_id
        // is ON DELETE CASCADE, so the join rows findQuestionSetIdsContaining() needs are gone
        // as soon as the DELETE runs — invalidating after remove() would silently do nothing.
        $questionSetIds = $this->questionSets->findQuestionSetIdsContaining($id);
        $this->questions->remove($question);
        $this->invalidateQuestionSetsCache($questionSetIds);

        return $this->viewHandler->handle(View::create(null, 204));
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
