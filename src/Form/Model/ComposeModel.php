<?php

namespace Base\Mailbox\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

/** The compose form: recipients as typed usernames, a subject, the first message. */
class ComposeModel
{
    /** Comma-separated usernames; the service resolves and checks them. */
    #[Assert\NotBlank(message: '@mailbox.error.no_recipient', groups: ['usernames'])]
    public ?string $recipients = null;

    /** With mailbox.directory: a choice of the directory, "desk:<name>" or "user:<id>". */
    #[Assert\NotBlank(message: '@mailbox.error.no_recipient', groups: ['directory'])]
    public ?string $to = null;

    /** The longest: the form's subject_max_length option (mailbox.subject_max_length), ComposeType's constraint. */
    #[Assert\NotBlank(message: '@mailbox.error.empty')]
    public ?string $subject = null;

    #[Assert\NotBlank(message: '@mailbox.error.empty')]
    public ?string $content = null;

    /** @return string[] */
    public function getRecipientList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $this->recipients) ?: [])));
    }
}
