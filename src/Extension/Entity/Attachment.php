<?php

namespace Base\Mailbox\Extension\Entity;

use Base\Mailbox\Entity\Message;
use Base\Mailbox\Extension\Repository\AttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A file sent with a message: only where it is kept - the id of its
 * document in omnibase/office's encrypted vault - and whether it is a
 * picture to show in the conversation. Its name and content are the vault's.
 */
#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'mailbox_attachment')]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected Message $message;

    #[ORM\Column(type: 'integer')]
    protected int $documentId;

    #[ORM\Column(type: 'boolean')]
    protected bool $image = false;

    public function __construct(Message $message, int $documentId, bool $image = false)
    {
        $this->message = $message;
        $this->documentId = $documentId;
        $this->image = $image;
    }

    public function getId(): ?int { return $this->id; }
    public function getMessage(): Message { return $this->message; }
    public function getDocumentId(): int { return $this->documentId; }
    public function isImage(): bool { return $this->image; }
}
