<?php

namespace Base\Mailbox\Recipient;

use App\Entity\User;
use Base\Mailbox\Desk\Desks;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Whom a member may write to when mailbox.directory is on: the desks, then
 * the people the site's RecipientProviderInterface services offer. Choices
 * are "desk:<name>" and "user:<id>"; what comes back from the form is
 * looked up here again, never trusted.
 */
class Directory
{
    /** @var iterable<RecipientProviderInterface> */
    private readonly iterable $providers;

    /** @param iterable<RecipientProviderInterface> $providers */
    public function __construct(
        private readonly Desks $desks,
        #[AutowireIterator('mailbox.recipient_provider')] iterable $providers = [],
    ) {
        $this->providers = $providers;
    }

    /** @return array<string, string> label => choice */
    public function choices(User $sender): array
    {
        $choices = [];
        foreach ($this->desks->all() as $name => $label) {
            $choices[$label] = 'desk:'.$name;
        }
        foreach ($this->people($sender) as $label => $user) {
            $choices[$label] = 'user:'.$user->getId();
        }

        return $choices;
    }

    /** The desk's name, or the user, a choice stands for; null when it was not offered to this sender. */
    public function resolve(User $sender, ?string $choice): string|User|null
    {
        if (null === $choice) {
            return null;
        }
        if (str_starts_with($choice, 'desk:')) {
            $desk = substr($choice, 5);

            return $this->desks->has($desk) ? $desk : null;
        }
        if (str_starts_with($choice, 'user:')) {
            foreach ($this->people($sender) as $user) {
                if ((string) $user->getId() === substr($choice, 5)) {
                    return $user;
                }
            }
        }

        return null;
    }

    /** @return array<string, User> */
    private function people(User $sender): array
    {
        $people = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->recipients($sender) as $label => $user) {
                if ($user->getId() !== $sender->getId()) {
                    $people[$label] = $user;
                }
            }
        }

        return $people;
    }
}
