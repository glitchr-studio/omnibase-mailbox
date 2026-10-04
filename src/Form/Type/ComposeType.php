<?php

namespace Base\Mailbox\Form\Type;

use Base\Mailbox\Form\Model\ComposeModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComposeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ComposeModel::class,
            'translation_domain' => 'mailbox',
            'subject_max_length' => 55,
            // With mailbox.directory: label => choice (Base\Mailbox\Recipient\Directory); null: typed usernames, as ever.
            'directory' => null,
            'validation_groups' => static fn (FormInterface $form) => ['Default', null === $form->getConfig()->getOption('directory') ? 'usernames' : 'directory'],
        ]);
        $resolver->setAllowedTypes('directory', ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (null !== $options['directory']) {
            $builder->add('to', ChoiceType::class, [
                'label' => 'form.to',
                'choices' => $options['directory'],
                'choice_translation_domain' => false,
                'placeholder' => 1 === \count($options['directory']) ? false : 'form.to_choose',
            ]);
        } else {
            $builder->add('recipients', TextType::class, [
                'label' => 'form.to',
                'help' => 'form.to_help',
                'attr' => ['placeholder' => 'form.to_placeholder', 'autocomplete' => 'off', 'data-mailbox-recipients' => ''],
            ]);
        }
        $builder
            ->add('subject', TextType::class, [
                'label' => 'form.subject',
                'attr' => ['maxlength' => $options['subject_max_length'], 'placeholder' => 'form.subject_placeholder'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'form.message',
                'attr' => ['rows' => 10, 'placeholder' => 'form.message_placeholder'],
            ]);
    }
}
