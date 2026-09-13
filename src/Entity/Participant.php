<?php

namespace Base\Mailbox\Entity;

use App\Entity\User;
use Base\Mailbox\Repository\ParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One member's view of a conversation: when they last read it, whether they
 * archived or deleted it. The conversation itself is shared; this row is
 * theirs - so one member deleting a conversation never removes it from the
 * other's box.
 */
#[ORM\Entity(repositoryClass: ParticipantRepository::class)]
#[ORM\Table(name: 'mailbox_participant')]
#[ORM\UniqueConstraint(name: 'mailbox_participant_unique', columns: ['conversation_id', 'user_id'])]
#[ORM\Index(columns: ['user_id', 'deletedAt', 'archivedAt'], name: 'mailbox_participant_box_idx')]
class Participant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?User $user = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $lastReadAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $starred = false;

    public function __construct(?Conversation $conversation = null, ?User $user = null)
    {
        $this->conversation = $conversation;
        $this->user = $user;
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ?Conversation { return $this->conversation; }
    public function setConversation(?Conversation $conversation): self { $this->conversation = $conversation; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getLastReadAt(): ?\DateTimeInterface { return $this->lastReadAt; }
    public function markRead(?\DateTimeInterface $at = null): self
    {
        $this->lastReadAt = $at ?? new \DateTime();
        return $this;
    }

    public function isArchived(): bool { return null !== $this->archivedAt; }
    public function getArchivedAt(): ?\DateTimeInterface { return $this->archivedAt; }
    public function archive(): self { $this->archivedAt = new \DateTime(); return $this; }
    public function unarchive(): self { $this->archivedAt = null; return $this; }

    public function isDeleted(): bool { return null !== $this->deletedAt; }
    public function getDeletedAt(): ?\DateTimeInterface { return $this->deletedAt; }
    public function delete(): self { $this->deletedAt = new \DateTime(); return $this; }
    public function restore(): self { $this->deletedAt = null; return $this; }

    public function isStarred(): bool { return $this->starred; }
    public function setStarred(bool $starred): self { $this->starred = $starred; return $this; }
}
