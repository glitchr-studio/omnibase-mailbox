<?php

namespace Base\Mailbox\Extension\Entity;

use App\Entity\User;
use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Extension\Repository\DeskConversationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A conversation written to a desk rather than to someone: its author is
 * its only participant at first; whoever holds the desk's role reads it,
 * and joins it by answering. In a table of its own so that a site without
 * desks has nothing to migrate.
 */
#[ORM\Entity(repositoryClass: DeskConversationRepository::class)]
#[ORM\Table(name: 'mailbox_desk_conversation')]
#[ORM\Index(columns: ['desk'], name: 'mailbox_desk_idx')]
class DeskConversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\OneToOne(targetEntity: Conversation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected Conversation $conversation;

    #[ORM\Column(length: 64)]
    protected string $desk;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?User $author;

    public function __construct(Conversation $conversation, string $desk, ?User $author)
    {
        $this->conversation = $conversation;
        $this->desk = $desk;
        $this->author = $author;
    }

    public function getId(): ?int { return $this->id; }
    public function getConversation(): Conversation { return $this->conversation; }
    public function getDesk(): string { return $this->desk; }
    public function getAuthor(): ?User { return $this->author; }

    /** The author wrote last: the desk has something to answer. */
    public function isWaiting(): bool
    {
        return null !== $this->author && $this->conversation->getLastSender() === $this->author;
    }
}
