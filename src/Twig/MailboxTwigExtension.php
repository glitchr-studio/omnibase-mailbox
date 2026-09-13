<?php

namespace Base\Mailbox\Twig;

use App\Entity\User;
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
 */
final class MailboxTwigExtension extends AbstractExtension
{
    private ?int $unread = null;
    private bool $counted = false;

    public function __construct(private readonly Mailbox $mailbox, private readonly Security $security)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('mailbox_unread_count', [$this, 'unreadCount'])];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('mailbox_text', [$this, 'text'], ['is_safe' => ['html']])];
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

    public function text(?string $content): string
    {
        $safe = htmlspecialchars(trim((string) $content), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safe = preg_replace_callback('~(https?://[^\s<]+[^\s<.,;:!?)\]])~u', fn ($m) => '<a href="' . $m[1] . '" rel="nofollow noopener" target="_blank">' . $m[1] . '</a>', $safe) ?? $safe;

        $paragraphs = preg_split('/\R{2,}/u', $safe) ?: [$safe];

        return implode('', array_map(fn ($p) => '<p>' . nl2br($p) . '</p>', $paragraphs));
    }
}
