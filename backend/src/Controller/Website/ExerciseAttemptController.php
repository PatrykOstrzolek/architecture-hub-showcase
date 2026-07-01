<?php

declare(strict_types=1);

namespace App\Controller\Website;

use App\Assessment\Application\SubmitAttemptService;
use App\Assessment\Domain\Exception\ExerciseNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

readonly class ExerciseAttemptController
{
    private const VALID_OPTIONS = ['a', 'b', 'c', 'd'];

    /** Matches the exercise template's `maxOccurs="20"` on the `questions` block. */
    private const MAX_QUESTIONS = 20;

    public function __construct(private SubmitAttemptService $submitAttemptService)
    {
    }

    #[Route('/api/exercise-attempts', name: 'app.api.exercise_attempts', methods: ['POST'])]
    public function postAction(Request $request): JsonResponse
    {
        $body = \json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
        }

        $exerciseUuid = $body['exerciseUuid'] ?? null;
        $sessionId = $body['sessionId'] ?? null;
        $answers = $body['answers'] ?? null;

        if (!\is_string($exerciseUuid) || !$this->isUuid($exerciseUuid)
            || !\is_string($sessionId) || !$this->isUuid($sessionId)
            || !$this->isValidAnswers($answers)
        ) {
            return new JsonResponse(['error' => 'Invalid request payload.'], 400);
        }

        try {
            $result = $this->submitAttemptService->submit($exerciseUuid, $sessionId, $answers);
        } catch (ExerciseNotFoundException) {
            return new JsonResponse(['error' => 'Exercise not found.'], 404);
        }

        return new JsonResponse([
            'score' => $result->score,
            'total' => $result->total,
            'results' => $result->perQuestion,
        ]);
    }

    private function isUuid(string $value): bool
    {
        return 1 === \preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * @phpstan-assert-if-true list<string|null> $value
     */
    private function isValidAnswers(mixed $value): bool
    {
        if (!\is_array($value) || !\array_is_list($value) || \count($value) > self::MAX_QUESTIONS) {
            return false;
        }

        foreach ($value as $answer) {
            if (null !== $answer && !\in_array($answer, self::VALID_OPTIONS, true)) {
                return false;
            }
        }

        return true;
    }
}
