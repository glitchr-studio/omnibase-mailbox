<?php

namespace Base\Mailbox\Form\Type;

use Base\Mailbox\Form\Model\ComposeModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComposeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ComposeModel::class,
            'translation_domain' => 'mailbox',
            'subject_max_length' => 55,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipients', TextType::class, [
                'label' => 'form.to',
                'help' => 'form.to_help',
                'attr' => ['placeholder' => 'form.to_placeholder', 'autocomplete' => 'off', 'data-mailbox-recipients' => ''],
            ])
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
