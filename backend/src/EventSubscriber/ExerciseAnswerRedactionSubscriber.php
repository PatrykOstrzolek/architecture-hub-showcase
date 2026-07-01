<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Strips the answer key from the public headless JSON for `exercise` pages.
 * Grading is server-authoritative (see App\Assessment) — the initial page
 * load must not leak `correct`/`explanation` before the user submits.
 * Runs on kernel.response, before FOSHttpCacheBundle stores the response, so
 * the cached body never carries the answer key either.
 */
class ExerciseAnswerRedactionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        if (!\str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            return;
        }

        $content = $response->getContent();
        if (false === $content || '' === $content) {
            return;
        }

        $data = \json_decode($content, true);
        if (!\is_array($data) || 'exercise' !== ($data['template'] ?? null)) {
            return;
        }

        $data['content'] ??= null;
        if (!\is_array($data['content'])) {
            return;
        }

        $questions = $data['content']['questions'] ?? null;
        if (!\is_array($questions)) {
            return;
        }

        foreach ($questions as &$question) {
            if (\is_array($question)) {
                unset($question['correct'], $question['explanation']);
            }
        }
        unset($question);

        $data['content']['questions'] = $questions;
        $response->setContent((string) \json_encode($data));
    }
}
