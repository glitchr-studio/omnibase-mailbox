<?php

namespace Base\Mailbox\Twig;

use App\Entity\User;
use Base\Mailbox\Desk\Desks;
use Base\Mailbox\Service\Mailbox;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 *   mailbox_unread_count()   unread conversations of the signed-in member,
 *                            for a badge in the site's menu. Counted once per
 *                            request; null when nobody is signed in or the
 *                            count cannot be had - a menu must never 500.
 *   |mailbox_text            a message body as HTML: escaped, paragraphs and
 *                            line breaks kept, URLs made clickable.
 *   |mailbox_plain           a subject or a message as written (decrypted
 *                            when mailbox.encrypt stored it encrypted).
 *   mailbox_desks()          the desks the signed-in member answers at
 *                            (name => label); mailbox_desk_waiting() how many
 *                            of their conversations wait for an answer.
 */
final class MailboxTwigExtension extends AbstractExtension
{
    private ?int $unread = null;
    private bool $counted = false;

    public function __construct(private readonly Mailbox $mailbox, private readonly Security $security, private readonly ?Desks $desks = null)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mailbox_unread_count', [$this, 'unreadCount']),
            new TwigFunction('mailbox_desks', [$this, 'desks']),
            new TwigFunction('mailbox_desk_waiting', [$this, 'deskWaiting']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('mailbox_text', [$this, 'text'], ['is_safe' => ['html']]),
            new TwigFilter('mailbox_plain', [$this, 'plain']),
        ];
    }

    public function unreadCount(): ?int
    {
        if ($this->counted) {
            return $this->unread;
        }
        $this->counted = true;

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        try {
            $this->unread = $this->mailbox->countUnread($user);
        } catch (\Throwable) {
            $this->unread = null;
        }

        return $this->unread;
    }

    /** @return array<string, string> the desks the signed-in member answers at */
    public function desks(): array
    {
        $user = $this->security->getUser();
        if (null === $this->desks || !$user instanceof User) {
            return [];
        }
        $mine = [];
        foreach ($this->desks->forUser($user) as $name) {
            $mine[$name] = $this->desks->label($name);
        }

        return $mine;
    }

    public function deskWaiting(): int
    {
        $user = $this->security->getUser();
        try {
            return null !== $this->desks && $user instanceof User ? $this->desks->countWaiting($user) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /** As written; "…" in place of what cannot be decrypted. */
    public function plain(?string $stored): string
    {
        // No constructor in some tests, no mailbox then: the text as it is.
        $clear = isset($this->mailbox) ? $this->mailbox->reveal($stored) : $stored;

        return $clear ?? '…';
    }

    public function text(?string $content): string
    {
        $content = $this->plain($content);
        $safe = htmlspecialchars(trim((string) $content), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safe = preg_replace_callback('~(https?://[^\s<]+[^\s<.,;:!?)\]])~u', fn ($m) => '<a href="' . $m[1] . '" rel="nofollow noopener" target="_blank">' . $m[1] . '</a>', $safe) ?? $safe;

        $paragraphs = preg_split('/\R{2,}/u', $safe) ?: [$safe];

        return implode('', array_map(fn ($p) => '<p>' . nl2br($p) . '</p>', $paragraphs));
    }
}
