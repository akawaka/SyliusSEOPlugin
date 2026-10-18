<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Form\Extension;

use Akawaka\SyliusSEOPlugin\Robots\RobotsTxtManager;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelType;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/** Adds the unmapped `akawaka_robots_txt` field to the channel admin form. */
final class ChannelTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly RobotsTxtManager $robotsTxt)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ChannelType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $channel = $event->getData();
            if (!$channel instanceof ChannelInterface) {
                return;
            }

            $event->getForm()->add('akawaka_robots_txt', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'akawaka_seo.form.robots_txt',
                'help' => 'akawaka_seo.form.robots_txt_help',
                'data' => $this->robotsTxt->stored($channel->getCode()),
                'attr' => ['rows' => 10, 'placeholder' => $this->robotsTxt->defaultContent()],
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $channel = $event->getData();
            $form = $event->getForm();

            if ($channel instanceof ChannelInterface && $form->has('akawaka_robots_txt')) {
                $value = $form->get('akawaka_robots_txt')->getData();
                $this->robotsTxt->stash($channel, is_string($value) ? $value : '');
            }
        });
    }
}
