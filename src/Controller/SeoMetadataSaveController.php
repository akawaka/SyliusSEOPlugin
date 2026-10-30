<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Controller;

use Akawaka\SyliusSEOPlugin\Enum\AuditField;
use Akawaka\SyliusSEOPlugin\Persister\SeoMetadataPersister;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** Saves one SEO override (title, description, robots) of a product or taxon, called by the inline editor of the SEO audit. */
final class SeoMetadataSaveController
{
    private const TYPES = ['product', 'taxon'];

    public function __construct(
        private readonly SeoMetadataPersister $persister,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->csrf->isTokenValid(new CsrfToken(ImageAltSaveController::CSRF_ID, $request->request->getString('_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $type = $request->request->getString('type');
        $locale = $request->request->getString('locale');
        $id = $request->request->getInt('id');
        $field = AuditField::tryFrom($request->request->getString('field'));
        if (!in_array($type, self::TYPES, true) || '' === $locale || $id < 1 || null === $field) {
            throw new NotFoundHttpException('Unknown subject or field.');
        }

        $raw = trim($request->request->getString('value'));
        $value = match ($field) {
            AuditField::RobotsIndex => '' !== $raw,
            AuditField::MetaTitle => mb_substr($raw, 0, 255),
            AuditField::MetaDescription => $raw,
        };

        $this->persister->updateField($type, $id, $locale, $field, $value);

        return new JsonResponse(['value' => is_bool($value) ? ($value ? '1' : '') : $value]);
    }
}
