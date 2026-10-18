<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Form\Extension;

use Akawaka\SyliusSEOPlugin\Form\Type\SeoSubjectType;
use Akawaka\SyliusSEOPlugin\Persister\SeoMetadataPersister;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/** Adds the unmapped `akawaka_seo` field to the admin form of an SEO subject. */
abstract class AbstractSeoTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly SeoMetadataPersister $persister)
    {
    }

    abstract protected function subjectType(): string;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $subject = $event->getData();
            if (!is_object($subject)) {
                return;
            }

            $id = method_exists($subject, 'getId') ? $subject->getId() : null;
            $event->getForm()->add('akawaka_seo', SeoSubjectType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'data' => $this->persister->load($this->subjectType(), null === $id ? null : (int) $id),
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $subject = $event->getData();
            $form = $event->getForm();

            if (is_object($subject) && $form->has('akawaka_seo')) {
                $data = $form->get('akawaka_seo')->getData();
                $this->persister->stash($subject, is_array($data) ? $data : null);
            }
        });
    }
}
