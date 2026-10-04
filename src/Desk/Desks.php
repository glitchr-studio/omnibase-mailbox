<?php

namespace Base\Mailbox\Desk;

use App\Entity\User;
use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Extension\Entity\DeskConversation;
use Base\Mailbox\Extension\Repository\DeskConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * The desks (mailbox.desks): "the secretariat", "the front office". A
 * member writes to one without knowing who is behind it; whoever holds its
 * role - their own, a group's, or through the role hierarchy - reads its
 * conversations and answers them.
 */
class Desks
{
    /** @param array<string, array{role: string, label: ?string}> $desks */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoleHierarchyInterface $roles,
        #[Autowire('%mailbox.desks%')] private readonly array $desks = [],
    ) {
    }

    public function isEnabled(): bool
    {
        return [] !== $this->desks;
    }

    /** @return array<string, string> name => label */
    public function all(): array
    {
        $all = [];
        foreach ($this->desks as $name => $desk) {
            $all[$name] = $desk['label'] ?? ucfirst($name);
        }

        return $all;
    }

    public function has(string $desk): bool
    {
        return isset($this->desks[$desk]);
    }

    public function label(string $desk): string
    {
        return $this->desks[$desk]['label'] ?? ucfirst($desk);
    }

    /** @return list<string> the desks this user answers at */
    public function forUser(User $user): array
    {
        if ([] === $this->desks) {
            return [];
        }
        $held = $this->roles->getReachableRoleNames($user->getRoles());

        return array_values(array_filter(array_keys($this->desks), fn (string $name) => \in_array($this->desks[$name]['role'], $held, true)));
    }

    public function of(Conversation $conversation): ?DeskConversation
    {
        return $this->isEnabled() ? $this->conversations()->findOneByConversation($conversation) : null;
    }

    /** Whether the user reads this conversation as staff of its desk. */
    public function staffs(User $user, Conversation $conversation): bool
    {
        $desk = $this->of($conversation);

        return null !== $desk && \in_array($desk->getDesk(), $this->forUser($user), true);
    }

    public function countWaiting(User $user): int
    {
        return $this->isEnabled() ? $this->conversations()->countWaiting($this->forUser($user)) : 0;
    }

    /**
     * The repository, asked only where desks are configured: without them the
     * entity is not mapped (MailboxExtension::prepend) and must not be touched.
     */
    public function conversations(): DeskConversationRepository
    {
        return $this->entityManager->getRepository(DeskConversation::class);
    }
}
