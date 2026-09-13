<?php

namespace Base\Mailbox\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** A reply inside a conversation: the text only. */
class ReplyType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'mailbox']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', TextareaType::class, [
            'label' => 'form.reply',
            'attr' => ['rows' => 6, 'placeholder' => 'form.reply_placeholder'],
            'constraints' => [new Assert\NotBlank(message: '@mailbox.error.empty')],
        ]);
    }
}
