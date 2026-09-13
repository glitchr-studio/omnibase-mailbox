<?php

namespace Base\Mailbox\Service;

/** A refusal the member should read: the message is a translation key in the "mailbox" domain. */
class MailboxException extends \RuntimeException
{
    public function __construct(string $key, private readonly array $parameters = [])
    {
        parent::__construct($key);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
