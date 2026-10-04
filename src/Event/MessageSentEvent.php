<?php

namespace Base\Mailbox\Event;

use App\Entity\User;
use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Entity\Message;
use Symfony\Contracts\EventDispatcher\Event;

/** A message was sent - a new conversation's first, or a reply. $desk: the desk it was written to, if any. */
final class MessageSentEvent extends Event
{
    public function __construct(
        public readonly Conversation $conversation,
        public readonly Message $message,
        public readonly User $sender,
        public readonly ?string $desk = null,
        public readonly bool $first = false,
    ) {
    }
}
