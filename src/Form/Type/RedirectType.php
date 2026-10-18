<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Form\Type;

use Akawaka\SyliusSEOPlugin\Enum\RedirectStatus;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class RedirectType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sourcePath', TextType::class, [
                'label' => 'akawaka_seo.redirect.source_path.label',
                'help' => 'akawaka_seo.redirect.source_path.help',
            ])
            ->add('targetUrl', TextType::class, [
                'label' => 'akawaka_seo.redirect.target_url.label',
                'help' => 'akawaka_seo.redirect.target_url.help',
            ])
            ->add('status', EnumType::class, [
                'class' => RedirectStatus::class,
                'choice_label' => static fn (RedirectStatus $status): string => $status->label(),
                'label' => 'akawaka_seo.redirect.status.label',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
        ;
    }
}
