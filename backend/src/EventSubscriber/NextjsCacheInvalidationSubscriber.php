<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Sulu\Article\Domain\Event\ArticleWorkflowTransitionAppliedEvent;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Page\Domain\Event\PageWorkflowTransitionAppliedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fires a POST /api/revalidate request to the Next.js frontend whenever a page or article
 * is published or unpublished in Sulu, invalidating the Next.js Data Cache immediately
 * instead of waiting for the 60-second time-based TTL to expire.
 *
 * Requires NEXT_REVALIDATE_URL and NEXT_REVALIDATE_SECRET to be set in the environment.
 * Both vars default to empty string (subscriber is a no-op if unconfigured).
 */
class NextjsCacheInvalidationSubscriber implements EventSubscriberInterface
{
    private const PUBLISH_TRANSITIONS = [
        WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
        WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH,
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $nextRevalidateUrl,
        private readonly string $nextRevalidateSecret,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PageWorkflowTransitionAppliedEvent::class => 'onPageTransition',
            ArticleWorkflowTransitionAppliedEvent::class => 'onArticleTransition',
        ];
    }

    public function onPageTransition(PageWorkflowTransitionAppliedEvent $event): void
    {
        if (\in_array($event->getWorkflowTransitionName(), self::PUBLISH_TRANSITIONS, true)) {
            $this->revalidate();
        }
    }

    public function onArticleTransition(ArticleWorkflowTransitionAppliedEvent $event): void
    {
        if (\in_array($event->getWorkflowTransitionName(), self::PUBLISH_TRANSITIONS, true)) {
            $this->revalidate();
        }
    }

    private function revalidate(): void
    {
        if ('' === $this->nextRevalidateUrl || '' === $this->nextRevalidateSecret) {
            return;
        }

        try {
            $response = $this->httpClient->request('POST', $this->nextRevalidateUrl . '/api/revalidate', [
                'headers' => ['Authorization' => 'Bearer ' . $this->nextRevalidateSecret],
                'timeout' => 5,
            ]);
            $status = $response->getStatusCode();
            if (200 !== $status) {
                $this->logger->warning('Next.js revalidation returned unexpected status.', ['status' => $status]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Next.js cache revalidation failed.', ['error' => $e->getMessage()]);
        }
    }
}
