<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Form\Type;

use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** One SeoMetadataType per enabled locale, keyed by locale code. */
final class SeoSubjectType extends AbstractType
{
    public function __construct(private readonly RepositoryInterface $localeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var LocaleInterface $locale */
        foreach ($this->localeRepository->findAll() as $locale) {
            $builder->add((string) $locale->getCode(), SeoMetadataType::class, ['label' => $locale->getName()]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The admin forms validate with the `sylius` group, which would skip the constraints of these unmapped fields.
        $resolver->setDefaults(['validation_groups' => ['Default']]);
    }
}
