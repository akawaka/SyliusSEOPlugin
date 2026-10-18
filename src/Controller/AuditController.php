<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Controller;

use Akawaka\SyliusSEOPlugin\Audit\SeoAuditor;
use Akawaka\SyliusSEOPlugin\Enum\AuditSeverity;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class AuditController
{
    /** @param ChannelRepositoryInterface<\Sylius\Component\Channel\Model\ChannelInterface> $channelRepository */
    public function __construct(
        private readonly SeoAuditor $auditor,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $channels = $this->channelRepository->findAll();
        $channel = $request->query->has('channel')
            ? $this->channelRepository->findOneByCode($request->query->getString('channel'))
            : ($channels[0] ?? null);

        if (!$channel instanceof ChannelInterface) {
            throw new NotFoundHttpException('No channel to audit.');
        }

        $locales = [];
        foreach ($channel->getLocales() as $channelLocale) {
            $locales[] = (string) $channelLocale->getCode();
        }
        $locale = $request->query->getString('locale', (string) $channel->getDefaultLocale()?->getCode());
        if (!in_array($locale, $locales, true)) {
            $locale = $locales[0] ?? 'en_US';
        }

        $issues = $this->auditor->audit($channel, $locale);
        $counts = [];
        foreach (AuditSeverity::cases() as $severity) {
            $counts[$severity->value] = count(array_filter($issues, static fn ($issue): bool => $issue->rule->severity() === $severity));
        }

        return new Response($this->twig->render('@AkawakaSyliusSEOPlugin/admin/audit/index.html.twig', [
            'channels' => $channels,
            'channel' => $channel,
            'locales' => $locales,
            'locale' => $locale,
            'issues' => $issues,
            'counts' => $counts,
        ]));
    }
}
