<?php

namespace Base\Mailbox\Service;

use App\Entity\User;
use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Entity\Message;
use Base\Mailbox\Repository\ConversationRepository;
use Base\Mailbox\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The rules of the mailbox, in one place: who may write to whom, how often,
 * how long a subject may be, how full a box may get. The controller only
 * turns a refusal into a flash.
 *
 * The limits are the historical site's: 100 conversations in a box, two
 * minutes between two messages, a 55-character subject.
 */
class Mailbox
{
    private readonly ConversationRepository $conversations;
    private readonly MessageRepository $messages;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        #[Autowire('%mailbox.inbox_limit%')] private readonly int $inboxLimit = 100,
        #[Autowire('%mailbox.flood_interval%')] private readonly int $floodInterval = 120,
        #[Autowire('%mailbox.subject_max_length%')] private readonly int $subjectMaxLength = 55,
        #[Autowire('%mailbox.content_max_length%')] private readonly int $contentMaxLength = 10000,
        #[Autowire('%mailbox.max_recipients%')] private readonly int $maxRecipients = 5,
    ) {
        $this->conversations = $entityManager->getRepository(Conversation::class);
        $this->messages = $entityManager->getRepository(Message::class);
    }

    /**
     * Start a conversation. Recipients are usernames, typed by the sender,
     * matched case-insensitively.
     *
     * @param string[] $usernames
     * @throws MailboxException
     */
    public function compose(User $sender, array $usernames, string $subject, string $content): Conversation
    {
        $subject = trim($subject);
        $content = trim($content);
        if ('' === $subject || '' === $content) {
            throw new MailboxException('error.empty');
        }
        if (mb_strlen($subject) > $this->subjectMaxLength) {
            throw new MailboxException('error.subject_too_long', ['%max%' => $this->subjectMaxLength]);
        }
        $this->assertContent($content);

        $recipients = $this->resolveRecipients($sender, $usernames);
        $this->assertNotFlooding($sender);

        foreach ($recipients as $recipient) {
            $this->assertHasRoom($recipient);
        }

        $conversation = new Conversation($subject);
        $conversation->addParticipant($sender);
        foreach ($recipients as $recipient) {
            $conversation->addParticipant($recipient);
        }
        $conversation->addMessage(new Message($sender, $content));

        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return $conversation;
    }

    /** @throws MailboxException */
    public function reply(User $sender, Conversation $conversation, string $content): Message
    {
        $content = trim($content);
        if ('' === $content) {
            throw new MailboxException('error.empty');
        }
        $this->assertContent($content);

        if (!$conversation->hasParticipant($sender) || $conversation->getParticipant($sender)?->isDeleted()) {
            throw new MailboxException('error.not_yours');
        }
        if ([] === $conversation->getOthers($sender)) {
            throw new MailboxException('error.nobody_left');
        }
        $this->assertNotFlooding($sender);

        $message = new Message($sender, $content);
        $conversation->addMessage($message);

        // A reply resurfaces the conversation for everyone who had deleted
        // it, as mail does: the answer arrives whether or not the earlier
        // exchange was kept.
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getUser() !== $sender && $participant->isDeleted()) {
                $participant->restore();
            }
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function markRead(User $user, Conversation $conversation): void
    {
        $participant = $conversation->getParticipant($user);
        if ($participant && $conversation->isUnreadFor($user)) {
            $participant->markRead();
            $this->entityManager->flush();
        }
    }

    public function archive(User $user, Conversation $conversation, bool $archive = true): void
    {
        $participant = $conversation->getParticipant($user);
        if ($participant) {
            $archive ? $participant->archive() : $participant->unarchive();
            $this->entityManager->flush();
        }
    }

    public function star(User $user, Conversation $conversation, bool $starred): void
    {
        $participant = $conversation->getParticipant($user);
        if ($participant) {
            $participant->setStarred($starred);
            $this->entityManager->flush();
        }
    }

    /**
     * Delete for this member only. When nobody keeps it any more the
     * conversation itself goes.
     */
    public function delete(User $user, Conversation $conversation): void
    {
        $participant = $conversation->getParticipant($user);
        if (!$participant) {
            return;
        }
        $participant->delete();

        $kept = false;
        foreach ($conversation->getParticipants() as $other) {
            if (!$other->isDeleted()) {
                $kept = true;
                break;
            }
        }
        if (!$kept) {
            $this->entityManager->remove($conversation);
        }

        $this->entityManager->flush();
    }

    public function countUnread(User $user): int
    {
        return $this->conversations->countUnread($user);
    }

    public function getInboxLimit(): int
    {
        return $this->inboxLimit;
    }

    public function getSubjectMaxLength(): int
    {
        return $this->subjectMaxLength;
    }

    /**
     * @param string[] $usernames
     * @return User[]
     * @throws MailboxException
     */
    private function resolveRecipients(User $sender, array $usernames): array
    {
        $usernames = array_values(array_unique(array_filter(array_map(fn ($u) => trim((string) $u), $usernames))));
        if ([] === $usernames) {
            throw new MailboxException('error.no_recipient');
        }
        if (count($usernames) > $this->maxRecipients) {
            throw new MailboxException('error.too_many_recipients', ['%max%' => $this->maxRecipients]);
        }

        $users = $this->entityManager->getRepository(User::class);
        $recipients = [];
        foreach ($usernames as $username) {
            $recipient = $users->createQueryBuilder('u')
                ->andWhere('LOWER(u.username) = :username')->setParameter('username', mb_strtolower($username))
                ->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();

            if (!$recipient instanceof User) {
                throw new MailboxException('error.unknown_recipient', ['%username%' => $username]);
            }
            if ($recipient === $sender) {
                throw new MailboxException('error.self');
            }
            $recipients[$recipient->getId()] = $recipient;
        }

        return array_values($recipients);
    }

    /** @throws MailboxException */
    private function assertContent(string $content): void
    {
        if (mb_strlen($content) > $this->contentMaxLength) {
            throw new MailboxException('error.content_too_long', ['%max%' => $this->contentMaxLength]);
        }
    }

    /** @throws MailboxException */
    private function assertNotFlooding(User $sender): void
    {
        if ($this->floodInterval <= 0 || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $last = $this->messages->findLastBySender($sender);
        if ($last && $last->getCreatedAt()) {
            $wait = $this->floodInterval - (time() - $last->getCreatedAt()->getTimestamp());
            if ($wait > 0) {
                throw new MailboxException('error.flood', ['%seconds%' => $wait]);
            }
        }
    }

    /** @throws MailboxException */
    private function assertHasRoom(User $recipient): void
    {
        if ($this->inboxLimit > 0 && $this->conversations->countInbox($recipient) >= $this->inboxLimit) {
            throw new MailboxException('error.inbox_full', ['%username%' => (string) $recipient->getUsername()]);
        }
    }
}
