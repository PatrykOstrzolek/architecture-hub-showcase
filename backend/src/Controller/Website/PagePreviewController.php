<?php

declare(strict_types=1);

namespace App\Controller\Website;

use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Exception\PageNotFoundException;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves draft-stage page content in the same headless JSON shape as the public
 * `.json` route, so the Next.js frontend can render unpublished edits via Next's
 * Draft Mode. Unlike the public route, this always resolves the DRAFT dimension
 * content — it must stay behind the bearer secret check, since draft content can
 * include unpublished text. See ADR-0013.
 *
 * Deliberately under `/admin/api/...`, not `/api/...`: any request whose path
 * matches `/admin(/|$)` gets Sulu's ADMIN kernel context (see public/index.php),
 * which — unlike the WEBSITE context — is never wrapped in SuluHttpCache's
 * reverse-proxy kernel in prod. A plain `/api/...` route hit this reverse-proxy
 * layer and 500'd in production despite working locally (APP_ENV=dev never
 * wraps the kernel at all, so the bug was invisible there). Matches the
 * existing convention: Sulu's own preview-link REST API lives under
 * `/admin/api/preview-links/*` for the same reason.
 */
readonly class PagePreviewController
{
    public function __construct(
        private RouteRepositoryInterface $routeRepository,
        private PageRepositoryInterface $pageRepository,
        private ContentManagerInterface $contentManager,
        private StructureResolverInterface $structureResolver,
        private string $previewSecret,
    ) {
    }

    #[Route('/admin/api/preview/pages', name: 'app.admin_api.preview_pages', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $path = $request->query->get('path');
        $locale = $request->query->get('locale', 'en');
        $webspace = $request->query->get('webspace', 'architecture-hub');

        if (!\is_string($path) || '' === $path) {
            return new JsonResponse(['error' => 'Missing "path" query parameter.'], 400);
        }

        $route = $this->routeRepository->findOneBy([
            'slug' => $path,
            'webspace' => $webspace,
            'locale' => $locale,
        ]);

        if (null === $route || PageInterface::RESOURCE_KEY !== $route->getResourceKey()) {
            return new JsonResponse(['error' => 'Page not found.'], 404);
        }

        try {
            $page = $this->pageRepository->getOneBy(
                ['uuid' => $route->getResourceId(), 'locale' => $locale],
                ['with-page-content' => [
                    'dimensionAttributes' => ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT],
                ]],
            );
        } catch (PageNotFoundException) {
            return new JsonResponse(['error' => 'Page not found.'], 404);
        }

        $dimensionContent = $this->contentManager->resolve($page, [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ]);

        $data = $this->structureResolver->resolve($dimensionContent, $locale);
        $data['localizations'] ??= [];

        $response = new JsonResponse($data);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function isAuthorized(Request $request): bool
    {
        if ('' === $this->previewSecret) {
            return false;
        }

        $header = $request->headers->get('Authorization', '');
        $token = \str_starts_with($header, 'Bearer ') ? \substr($header, 7) : '';

        return '' !== $token && \hash_equals($this->previewSecret, $token);
    }
}
