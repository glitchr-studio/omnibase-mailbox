<?php

namespace Base\Mailbox\Attachment\Office;

use App\Entity\User;
use Base\Mailbox\Desk\Desks;
use Base\Mailbox\Entity\Conversation;
use Base\Office\Entity\Share\Document;
use Base\Office\Share\AudienceResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who reads a conversation's attachments in the vault: whoever takes part
 * in the conversation, and the staff of the desk it was written to. Nobody
 * withdraws one from here.
 */
final class ConversationAudience implements AudienceResolverInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly Desks $desks)
    {
    }

    public function decide(string $attribute, Document $document, UserInterface $user): ?bool
    {
        $context = (string) $document->getContext();
        if (!str_starts_with($context, VaultAttachments::CONTEXT) || self::REVOKE === $attribute || !$user instanceof User) {
            return null;
        }
        $conversation = $this->entityManager->getRepository(Conversation::class)->find((int) substr($context, \strlen(VaultAttachments::CONTEXT)));
        if (!$conversation instanceof Conversation) {
            return null;
        }
        $participant = $conversation->getParticipant($user);

        return (null !== $participant && !$participant->isDeleted()) || $this->desks->staffs($user, $conversation) ? true : null;
    }
}
