<?php

namespace Base\Mailbox\Recipient;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Whom a member may write to by name rather than by username, when
 * mailbox.directory is on: a site's own rule - a patient's practitioners,
 * a tenant's manager. Autoconfigured (tag mailbox.recipient_provider).
 */
#[AutoconfigureTag('mailbox.recipient_provider')]
interface RecipientProviderInterface
{
    /** @return array<string, User> a label => the account */
    public function recipients(User $sender): array;
}
