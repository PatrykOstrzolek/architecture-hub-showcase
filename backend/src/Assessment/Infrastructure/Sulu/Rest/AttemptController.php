<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Attempt;
use App\Assessment\Domain\Repository\AttemptRepositoryInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only admin view of the "Attempts" resource — grades captured by
 * ExerciseAttemptController (see ADR-0012) are graded server-side and
 * written once; nothing in the admin ever creates or edits an Attempt, so
 * this only exposes list + delete (for removing stale/test data).
 *
 * Unlike the rest of this codebase's controllers (plain JsonResponse, no
 * FOSRestBundle — see QuestionController's docblock), this one uses Sulu's
 * documented admin-controller pattern (ViewHandlerInterface + View::create(),
 * per docs.sulu.io/2.x/book/extend-admin.html's EventController example,
 * mirroring Sulu's own TagController): it's an *admin* endpoint consumed
 * only by the Sulu Admin SPA, not the public headless API the frontend
 * reads (that boundary is exactly where ADR-0012's "no Sulu content
 * rendering leaks into Assessment" ACL applies in reverse — this is Sulu
 * admin plumbing, not a headless response, so it's fine to lean on Sulu's
 * own machinery here). ViewHandler routes the response through Symfony
 * Serializer, which formats \DateTimeInterface as ISO 8601 automatically —
 * no manual createdAt formatting needed, unlike the JsonResponse approach.
 */
readonly class AttemptController
{
    public function __construct(
        private AttemptRepositoryInterface $attempts,
        private RestHelperInterface $restHelper,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private ViewHandlerInterface $viewHandler,
    ) {
    }

    #[Route('/admin/api/attempts', name: 'app.admin_api.attempts.list', methods: ['GET'], format: 'json')]
    public function listAction(Request $request): Response
    {
        ListPageAndLimitSanitizer::sanitize($request);

        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors('attempts');
        if (null === $fieldDescriptors) {
            throw new \LogicException('No field descriptors registered for list key "attempts" — check config/lists/attempts.xml.');
        }

        $listBuilder = $this->listBuilderFactory->create(Attempt::class);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $representation = new PaginatedRepresentation(
            $listBuilder->execute(),
            'attempts',
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->viewHandler->handle(View::create($representation));
    }

    #[Route('/admin/api/attempts/{id}', name: 'app.admin_api.attempts.delete', methods: ['DELETE'], format: 'json')]
    public function deleteAction(int $id): Response
    {
        $attempt = $this->attempts->find($id);
        if (null === $attempt) {
            return $this->viewHandler->handle(View::create(['error' => 'Attempt not found.'], 404));
        }

        $this->attempts->remove($attempt);

        return $this->viewHandler->handle(View::create(null, 204));
    }
}
