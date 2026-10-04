<?php

namespace Base\Mailbox\Attachment\Office;

use App\Entity\User;
use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Entity\Message;
use Base\Mailbox\Extension\Entity\Attachment;
use Base\Mailbox\Service\MailboxException;
use Base\Office\Enum\DocumentKind;
use Base\Office\Exception\KeyMissingException;
use Base\Office\Exception\ShareException;
use Base\Office\Share\DocumentVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A message's attachment kept in omnibase/office's encrypted vault (when
 * mailbox.attachments is on and omnibase/office installed): the file is a
 * Document of the conversation ("mailbox:<id>"), read by its participants
 * through ConversationAudience and logged like any other. The vault does
 * not e-mail about it: the message's own notice does.
 */
class VaultAttachments
{
    public const CONTEXT = 'mailbox:';

    public function __construct(
        private readonly DocumentVault $vault,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%mailbox.attachments%')] private readonly bool $enabled = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param User $owner whose document it is: the other participant, or the sender's own when written to a desk
     *
     * @throws MailboxException
     */
    public function attach(Message $message, UploadedFile $file, User $sender, User $owner): Attachment
    {
        try {
            $document = $this->vault->deposit($file, $owner, $sender, $file->getClientOriginalName(), DocumentKind::OTHER, context: self::CONTEXT.$message->getConversation()?->getId(), notify: false);
        } catch (ShareException $e) {
            throw new MailboxException('error.attachment', ['%reason%' => $e->getKey()]);
        } catch (KeyMissingException) {
            throw new MailboxException('error.no_key');
        }
        $attachment = new Attachment($message, (int) $document->getId(), $document->isImage());
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        return $attachment;
    }

    /** @return array<int, list<Attachment>> by message id */
    public function of(Conversation $conversation): array
    {
        return $this->enabled ? $this->entityManager->getRepository(Attachment::class)->findByConversation($conversation) : [];
    }
}
