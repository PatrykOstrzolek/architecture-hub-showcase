<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
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
 * Admin CRUD for the "Question Sets" resource. Save receives an ordered
 * list of existing Question ids (the generic multi-selection field's
 * value) and rebuilds QuestionSetItem rows — clear-and-rebuild rather than
 * diffing, matching this project's simplicity preference. Unresolvable
 * question ids are silently dropped (tolerant, matches this codebase's
 * existing "never throws on bad input" grading philosophy).
 *
 * Uses Sulu's documented admin pattern (ViewHandlerInterface +
 * View::create()) rather than plain JsonResponse — see QuestionController's
 * docblock for the full reasoning (admin-only vs. headless boundary).
 */
readonly class QuestionSetController
{
    public function __construct(
        private QuestionSetRepositoryInterface $questionSets,
        private QuestionRepositoryInterface $questions,
        private CacheInterface $cache,
        private RestHelperInterface $restHelper,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private ViewHandlerInterface $viewHandler,
    ) {
    }

    #[Route('/admin/api/question-sets', name: 'app.admin_api.question_sets.list', methods: ['GET'], format: 'json')]
    public function listAction(Request $request): Response
    {
        $ids = IdListQueryParser::parseAndSanitizeRequest($request);
        ListPageAndLimitSanitizer::sanitize($request);

        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors('question_sets');
        if (null === $fieldDescriptors) {
            throw new \LogicException('No field descriptors registered for list key "question_sets" — check config/lists/question_sets.xml.');
        }

        $listBuilder = $this->listBuilderFactory->create(QuestionSet::class);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        if ([] !== $ids) {
            // See QuestionController::listAction() for the full reasoning:
            // matches Sulu's own TagController flat=true branch, and the
            // client-side MultiSelectionStore.js re-sorts by requested id
            // order itself, so DoctrineListBuilder not preserving order here
            // is a non-issue (verified by hand).
            $listBuilder->limit(\count($ids));

            return $this->viewHandler->handle(View::create(new CollectionRepresentation($listBuilder->execute(), 'question_sets')));
        }

        $representation = new PaginatedRepresentation(
            $listBuilder->execute(),
            'question_sets',
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->viewHandler->handle(View::create($representation));
    }

    #[Route('/admin/api/question-sets/{id}', name: 'app.admin_api.question_sets.get', methods: ['GET'], format: 'json')]
    public function showAction(int $id): Response
    {
        $questionSet = $this->questionSets->findWithQuestions($id);
        if (null === $questionSet) {
            return $this->viewHandler->handle(View::create(['error' => 'Question set not found.'], 404));
        }

        return $this->viewHandler->handle(View::create($this->toDetail($questionSet)));
    }

    #[Route('/admin/api/question-sets', name: 'app.admin_api.question_sets.post', methods: ['POST'], format: 'json')]
    public function createAction(Request $request): Response
    {
        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return $this->viewHandler->handle(View::create(['error' => 'Invalid request payload.'], 400));
        }

        $questionSet = new QuestionSet(\is_string($data['title'] ?? null) ? $data['title'] : '');
        $this->applyQuestions($questionSet, $data['questionIds'] ?? []);
        $this->questionSets->save($questionSet);

        $newId = $questionSet->getId();
        if (null !== $newId) {
            $this->cache->delete(QuestionSetCacheKey::for($newId));
        }

        return $this->viewHandler->handle(View::create($this->toDetail($questionSet)));
    }

    #[Route('/admin/api/question-sets/{id}', name: 'app.admin_api.question_sets.put', methods: ['PUT'], format: 'json')]
    public function updateAction(int $id, Request $request): Response
    {
        $questionSet = $this->questionSets->findWithQuestions($id);
        if (null === $questionSet) {
            return $this->viewHandler->handle(View::create(['error' => 'Question set not found.'], 404));
        }

        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return $this->viewHandler->handle(View::create(['error' => 'Invalid request payload.'], 400));
        }

        $questionSet->update(\is_string($data['title'] ?? null) ? $data['title'] : $questionSet->getTitle());
        $this->applyQuestions($questionSet, $data['questionIds'] ?? []);
        $this->questionSets->save($questionSet);
        $this->cache->delete(QuestionSetCacheKey::for($id));

        return $this->viewHandler->handle(View::create($this->toDetail($questionSet)));
    }

    #[Route('/admin/api/question-sets/{id}', name: 'app.admin_api.question_sets.delete', methods: ['DELETE'], format: 'json')]
    public function deleteAction(int $id): Response
    {
        $questionSet = $this->questionSets->find($id);
        if (null === $questionSet) {
            return $this->viewHandler->handle(View::create(['error' => 'Question set not found.'], 404));
        }

        $this->questionSets->remove($questionSet);
        $this->cache->delete(QuestionSetCacheKey::for($id));

        return $this->viewHandler->handle(View::create(null, 204));
    }

    private function applyQuestions(QuestionSet $questionSet, mixed $rawQuestionIds): void
    {
        $ids = \is_array($rawQuestionIds) ? $rawQuestionIds : [];
        $validIds = \array_values(\array_filter($ids, static fn (mixed $id): bool => \is_int($id)));
        // findByIds() batches this into one query (preserving $validIds' order)
        // instead of one find() call per submitted id.
        $questionSet->replaceQuestions($this->questions->findByIds($validIds));
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(QuestionSet $questionSet): array
    {
        return [
            'id' => $questionSet->getId(),
            'title' => $questionSet->getTitle(),
            'questionIds' => \array_map(
                static fn (Question $question): ?int => $question->getId(),
                $questionSet->getOrderedQuestions(),
            ),
        ];
    }
}
