<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use Sulu\Component\Rest\ListBuilder\CollectionRepresentation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin CRUD for the "Question Sets" resource. Save receives an ordered
 * list of existing Question ids (the generic multi-selection field's
 * value) and rebuilds QuestionSetItem rows — clear-and-rebuild rather than
 * diffing, matching this project's simplicity preference. Unresolvable
 * question ids are silently dropped (tolerant, matches this codebase's
 * existing "never throws on bad input" grading philosophy).
 */
readonly class QuestionSetController
{
    public function __construct(
        private QuestionSetRepositoryInterface $questionSets,
        private QuestionRepositoryInterface $questions,
    ) {
    }

    #[Route('/admin/api/question-sets', name: 'app.admin_api.question_sets.list', methods: ['GET'])]
    public function cgetAction(Request $request): JsonResponse
    {
        $ids = IdListQueryParser::parse($request->query->get('ids', ''));
        $questionSets = [] !== $ids ? $this->questionSets->findByIds($ids) : $this->questionSets->findAll();
        $items = \array_map($this->toListItem(...), $questionSets);

        return new JsonResponse((new CollectionRepresentation($items, 'question_sets'))->toArray());
    }

    #[Route('/admin/api/question-sets/{id}', name: 'app.admin_api.question_sets.get', methods: ['GET'])]
    public function getAction(int $id): JsonResponse
    {
        $questionSet = $this->questionSets->findWithQuestions($id);
        if (null === $questionSet) {
            return new JsonResponse(['error' => 'Question set not found.'], 404);
        }

        return new JsonResponse($this->toDetail($questionSet));
    }

    #[Route('/admin/api/question-sets', name: 'app.admin_api.question_sets.post', methods: ['POST'])]
    public function postAction(Request $request): JsonResponse
    {
        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid request payload.'], 400);
        }

        $questionSet = new QuestionSet(\is_string($data['title'] ?? null) ? $data['title'] : '');
        $this->applyQuestions($questionSet, $data['questionIds'] ?? []);
        $this->questionSets->save($questionSet);

        return new JsonResponse($this->toDetail($questionSet));
    }

    #[Route('/admin/api/question-sets/{id}', name: 'app.admin_api.question_sets.put', methods: ['PUT'])]
    public function putAction(int $id, Request $request): JsonResponse
    {
        $questionSet = $this->questionSets->findWithQuestions($id);
        if (null === $questionSet) {
            return new JsonResponse(['error' => 'Question set not found.'], 404);
        }

        $data = \json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid request payload.'], 400);
        }

        $questionSet->update(\is_string($data['title'] ?? null) ? $data['title'] : $questionSet->getTitle());
        $this->applyQuestions($questionSet, $data['questionIds'] ?? []);
        $this->questionSets->save($questionSet);

        return new JsonResponse($this->toDetail($questionSet));
    }

    #[Route('/admin/api/question-sets/{id}', name: 'app.admin_api.question_sets.delete', methods: ['DELETE'])]
    public function deleteAction(int $id): Response
    {
        $questionSet = $this->questionSets->find($id);
        if (null === $questionSet) {
            return new JsonResponse(['error' => 'Question set not found.'], 404);
        }

        $this->questionSets->remove($questionSet);

        return new Response(status: 204);
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
    private function toListItem(QuestionSet $questionSet): array
    {
        return [
            'id' => $questionSet->getId(),
            'title' => $questionSet->getTitle(),
        ];
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
