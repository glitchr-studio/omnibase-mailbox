<?php

namespace Base\Mailbox\Entity;

use App\Entity\User;
use Base\Database\Attribute\Timestamp;
use Base\Mailbox\Repository\ConversationRepository;
use Base\Traits\BaseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A conversation: a subject, its participants, its messages. Replies stay in
 * the conversation rather than starting a new one - what the old inbox did
 * with "rep_s"/"rep_eid" by hand.
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'mailbox_conversation')]
class Conversation
{
    use BaseTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(type: 'string', length: 255)]
    protected ?string $subject = null;

    #[ORM\OneToMany(targetEntity: Participant::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $participants;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    protected Collection $messages;

    #[ORM\Column(type: 'datetime')]
    #[Timestamp(on: 'create')]
    protected ?\DateTimeInterface $createdAt = null;

    /** When the last message arrived - the inbox sorts on it. */
    #[ORM\Column(type: 'datetime')]
    protected ?\DateTimeInterface $lastMessageAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    protected ?User $lastSender = null;

    #[ORM\Column(type: 'integer')]
    protected int $messageCount = 0;

    public function __construct(?string $subject = null)
    {
        $this->subject = $subject;
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->lastMessageAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->subject ?? '';
    }

    public function getId(): ?int { return $this->id; }

    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $subject): self { $this->subject = $subject; return $this; }

    /** @return Collection<int, Participant> */
    public function getParticipants(): Collection { return $this->participants; }

    public function addParticipant(User $user): Participant
    {
        if ($existing = $this->getParticipant($user)) {
            return $existing;
        }

        $participant = new Participant($this, $user);
        $this->participants[] = $participant;

        return $participant;
    }

    public function getParticipant(User $user): ?Participant
    {
        foreach ($this->participants as $participant) {
            if ($participant->getUser() === $user) {
                return $participant;
            }
        }

        return null;
    }

    public function hasParticipant(User $user): bool
    {
        return null !== $this->getParticipant($user);
    }

    /** @return User[] everyone in the conversation except $except */
    public function getOthers(?User $except = null): array
    {
        $others = [];
        foreach ($this->participants as $participant) {
            if ($participant->getUser() !== $except) {
                $others[] = $participant->getUser();
            }
        }

        return $others;
    }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection { return $this->messages; }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages[] = $message;
            $message->setConversation($this);
        }

        $this->messageCount = $this->messages->count();
        $this->lastMessageAt = $message->getCreatedAt() ?? new \DateTime();
        $this->lastSender = $message->getSender();

        // Whoever writes has read up to here; the others now have something new.
        if ($sender = $message->getSender()) {
            $this->getParticipant($sender)?->markRead($this->lastMessageAt)->unarchive();
        }
        foreach ($this->participants as $participant) {
            if ($participant->getUser() !== $sender) {
                $participant->unarchive();
            }
        }

        return $this;
    }

    public function getLastMessage(): ?Message
    {
        $last = $this->messages->last();
        return $last ?: null;
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getLastMessageAt(): ?\DateTimeInterface { return $this->lastMessageAt; }
    public function getLastSender(): ?User { return $this->lastSender; }
    public function getMessageCount(): int { return $this->messageCount; }

    /** Unread for this member: something arrived after they last read. */
    public function isUnreadFor(User $user): bool
    {
        $participant = $this->getParticipant($user);
        if (!$participant) {
            return false;
        }
        if ($this->lastSender === $user) {
            return false;
        }

        return null === $participant->getLastReadAt() || $participant->getLastReadAt() < $this->lastMessageAt;
    }
}
