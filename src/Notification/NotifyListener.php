<?php

namespace Base\Mailbox\Notification;

use Base\Mailbox\Event\MessageSentEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "A message awaits you", when mailbox.notify is on: each other participant
 * who kept the conversation is mailed a link to it - not its content, not
 * its subject, not who wrote. The desk's staff are not mailed: they see
 * what waits in their box.
 */
final class NotifyListener
{
    public function __construct(
        private readonly ?MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        #[Autowire('%mailbox.notify%')] private readonly bool $enabled = false,
        #[Autowire('%mailbox.sender%')] private readonly ?string $sender = null,
    ) {
    }

    #[AsEventListener]
    public function onMessageSent(MessageSentEvent $event): void
    {
        if (!$this->enabled || null === $this->mailer) {
            return;
        }
        foreach ($event->conversation->getParticipants() as $participant) {
            $user = $participant->getUser();
            if (null === $user || $user === $event->sender || $participant->isDeleted() || !$user->getEmail()) {
                continue;
            }
            $email = (new TemplatedEmail())
                ->to($user->getEmail())
                ->subject($this->translator->trans('email.notify.subject', [], 'mailbox'))
                ->htmlTemplate('@Mailbox/email/notify.html.twig')
                ->context(['url' => $this->urls->generate('mailbox_show', ['id' => $event->conversation->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]);
            if ($this->sender) {
                $email->from($this->sender);
            }
            $this->mailer->send($email);
        }
    }
}
