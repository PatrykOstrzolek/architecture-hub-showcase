<?php

declare(strict_types=1);

namespace App\Controller\Website;

use Sulu\Bundle\PreviewBundle\Domain\Repository\PreviewLinkRepositoryInterface;
use Sulu\Bundle\PreviewBundle\UserInterface\Controller\PublicPreviewController;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Overrides Sulu's public preview-link route (`GET /admin/p/{token}`, route name
 * `sulu_preview.public_preview` — the URL behind the admin's "Share > Generate
 * link" action) so that links to PAGE resources redirect straight into the real
 * Next.js frontend via Draft Mode, instead of Sulu's Twig fallback view. See
 * ADR-0013. Anything else (a future non-page preview-link provider) falls back
 * to Sulu's own PublicPreviewController, unmodified.
 *
 * Overriding here works because this is a *separate*, DB-token-backed public
 * route — unrelated to the authenticated in-admin live preview (`/admin/preview/*`,
 * the inline iframe and the "open in new tab" button), which this does not touch.
 *
 * This re-registers a bundle route under its exact original name — a standard
 * Symfony technique for overriding bundle routing — so it's coupled to Sulu's
 * internal route naming; re-verify `sulu_preview.public_preview` still resolves
 * here after a Sulu upgrade (`bin/console debug:router sulu_preview.public_preview`).
 */
readonly class PreviewLinkRedirectController
{
    public function __construct(
        private PreviewLinkRepositoryInterface $previewLinkRepository,
        private RouteRepositoryInterface $routeRepository,
        private PublicPreviewController $original,
        private string $frontendUrl,
        private string $previewSecret,
    ) {
    }

    #[Route('/admin/p/{token}', name: 'sulu_preview.public_preview', methods: ['GET'])]
    public function __invoke(string $token): Response
    {
        $previewLink = $this->previewLinkRepository->findByToken($token);

        if (null === $previewLink
            || PageInterface::RESOURCE_KEY !== $previewLink->getResourceKey()
            || '' === $this->frontendUrl
            || '' === $this->previewSecret
        ) {
            return $this->original->previewAction($token);
        }

        $route = $this->routeRepository->findOneBy([
            'resourceKey' => PageInterface::RESOURCE_KEY,
            'resourceId' => $previewLink->getResourceId(),
            'locale' => $previewLink->getLocale(),
        ]);

        if (null === $route) {
            return $this->original->previewAction($token);
        }

        $previewLink->increaseVisitCount();
        $this->previewLinkRepository->commit();

        $url = \rtrim($this->frontendUrl, '/') . '/api/preview?' . \http_build_query([
            'secret' => $this->previewSecret,
            'path' => $route->getSlug(),
        ]);

        return new RedirectResponse($url);
    }
}
