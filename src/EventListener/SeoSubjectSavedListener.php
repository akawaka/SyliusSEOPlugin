<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\EventListener;

use Akawaka\SyliusSEOPlugin\Persister\SeoMetadataPersister;
use Symfony\Component\EventDispatcher\GenericEvent;

/** Writes the SEO rows collected by the admin form once the product / taxon has been saved. */
final class SeoSubjectSavedListener
{
    public function __construct(private readonly SeoMetadataPersister $persister)
    {
    }

    public function __invoke(GenericEvent $event): void
    {
        $subject = $event->getSubject();

        if (is_object($subject)) {
            $this->persister->persist($subject);
        }
    }
}
