<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\EventListener;

use Akawaka\SyliusSEOPlugin\Persister\SeoMetadataPersister;
use Symfony\Component\EventDispatcher\GenericEvent;

/** Drops the SEO rows of a product / taxon once it has been deleted. */
final class SeoSubjectDeletedListener
{
    public function __construct(private readonly SeoMetadataPersister $persister)
    {
    }

    public function onPreDelete(GenericEvent $event): void
    {
        $subject = $event->getSubject();

        if (is_object($subject)) {
            $this->persister->scheduleRemoval($subject);
        }
    }

    public function onPostDelete(GenericEvent $event): void
    {
        $subject = $event->getSubject();

        if (is_object($subject)) {
            $this->persister->removeScheduled($subject);
        }
    }
}
