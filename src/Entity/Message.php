<?php

namespace Base\Mailbox\Entity;

use App\Entity\User;
use Base\Database\Attribute\Timestamp;
use Base\Mailbox\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** One message of a conversation. Plain text; line breaks are kept, nothing else is interpreted. */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'mailbox_message')]
#[ORM\Index(columns: ['created_at'], name: 'mailbox_message_created_idx')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    protected ?User $sender = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: '@mailbox.message.blank')]
    protected ?string $content = null;

    #[ORM\Column(type: 'datetime')]
    #[Timestamp(on: 'create')]
    protected ?\DateTimeInterface $createdAt = null;

    public function __construct(?User $sender = null, ?string $content = null)
    {
        $this->sender = $sender;
        $this->content = $content;
    }

    public function __toString(): string
    {
        return mb_substr((string) $this->content, 0, 60);
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ?Conversation { return $this->conversation; }
    public function setConversation(?Conversation $conversation): self { $this->conversation = $conversation; return $this; }

    public function getSender(): ?User { return $this->sender; }
    public function setSender(?User $sender): self { $this->sender = $sender; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): self { $this->content = $content; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
