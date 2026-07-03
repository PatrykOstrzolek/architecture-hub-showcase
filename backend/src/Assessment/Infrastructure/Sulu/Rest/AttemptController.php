<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Attempt;
use App\Assessment\Domain\Repository\AttemptRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only admin view of the "Attempts" resource — grades captured by
 * ExerciseAttemptController (see ADR-0012) are graded server-side and
 * written once; nothing in the admin ever creates or edits an Attempt, so
 * this only exposes list + delete (for removing stale/test data).
 */
readonly class AttemptController
{
    use BuildsPaginatedRepresentation;

    public function __construct(private AttemptRepositoryInterface $attempts)
    {
    }

    #[Route('/admin/api/attempts', name: 'app.admin_api.attempts.list', methods: ['GET'])]
    public function cgetAction(Request $request): JsonResponse
    {
        $representation = $this->buildPaginatedRepresentation(
            $request,
            'attempts',
            fn (int $page, int $limit): array => \array_map(
                $this->toListItem(...),
                $this->attempts->findPaginated($page, $limit),
            ),
            fn (): int => $this->attempts->count(),
        );

        return new JsonResponse($representation->toArray());
    }

    #[Route('/admin/api/attempts/{id}', name: 'app.admin_api.attempts.delete', methods: ['DELETE'])]
    public function deleteAction(int $id): Response
    {
        $attempt = $this->attempts->find($id);
        if (null === $attempt) {
            return new JsonResponse(['error' => 'Attempt not found.'], 404);
        }

        $this->attempts->remove($attempt);

        return new Response(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Attempt $attempt): array
    {
        return [
            'id' => $attempt->getId(),
            'exerciseUuid' => $attempt->getExerciseUuid(),
            'sessionId' => $attempt->getSessionId(),
            'score' => $attempt->getScore(),
            'total' => $attempt->getTotal(),
            'createdAt' => $attempt->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
