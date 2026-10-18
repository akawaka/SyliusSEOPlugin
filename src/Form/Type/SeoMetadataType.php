<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

/** SEO overrides of one locale, edited as an array (the entity is created by the persister). */
final class SeoMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metaTitle', TextType::class, [
                'label' => 'akawaka_seo.form.meta_title',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'akawaka_seo.form.meta_description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('canonicalUrl', TextType::class, [
                'label' => 'akawaka_seo.form.canonical_url',
                'required' => false,
                'constraints' => [new Length(max: 2048), new Regex(pattern: '#^(https?://|/)#', message: 'akawaka_seo.form.url_format')],
            ])
            ->add('robotsIndex', CheckboxType::class, [
                'label' => 'akawaka_seo.form.robots_index',
                'required' => false,
            ])
            ->add('robotsFollow', CheckboxType::class, [
                'label' => 'akawaka_seo.form.robots_follow',
                'required' => false,
            ])
            ->add('ogTitle', TextType::class, [
                'label' => 'akawaka_seo.form.og_title',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('ogDescription', TextareaType::class, [
                'label' => 'akawaka_seo.form.og_description',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('ogImage', TextType::class, [
                'label' => 'akawaka_seo.form.og_image',
                'required' => false,
                'constraints' => [new Length(max: 2048), new Regex(pattern: '#^(https?://|/)#', message: 'akawaka_seo.form.url_format')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => false]);
    }
}
