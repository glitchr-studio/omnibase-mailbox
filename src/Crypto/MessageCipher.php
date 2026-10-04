<?php

namespace Base\Mailbox\Crypto;

use Base\Mailbox\Service\MailboxException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Subjects and messages encrypted at rest, when mailbox.encrypt is on:
 * libsodium's secretbox, "mbx1:" + base64(nonce + box) in the same columns.
 * A value without that mark is read as it is - what was written before
 * encryption was turned on stays readable.
 *
 * Fail closed: asked to encrypt without a usable key (mailbox.key, base64
 * of 32 bytes), it refuses - the message is not sent, never stored clear.
 */
class MessageCipher
{
    public const PREFIX = 'mbx1:';

    private readonly ?string $key;
    private ?string $secret = null;
    private bool $checked = false;

    public function __construct(
        #[Autowire('%mailbox.encrypt%')] private readonly bool $enabled = false,
        #[Autowire('%mailbox.key%')] #[\SensitiveParameter] ?string $key = null,
    ) {
        $this->key = $key;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** Encryption asked for and a key to do it with. */
    public function isReady(): bool
    {
        return !$this->enabled || null !== $this->secret();
    }

    /** @throws MailboxException error.no_key */
    public function encrypt(string $text): string
    {
        if (!$this->enabled) {
            return $text;
        }
        $secret = $this->secret() ?? throw new MailboxException('error.no_key');
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.base64_encode($nonce.sodium_crypto_secretbox($text, $nonce, $secret));
    }

    /** The clear text; null when it cannot be read (no key, another key, altered). */
    public function decrypt(?string $value): ?string
    {
        if (null === $value || !str_starts_with($value, self::PREFIX)) {
            return $value;
        }
        $secret = $this->secret();
        $raw = base64_decode(substr($value, \strlen(self::PREFIX)), true);
        if (null === $secret || false === $raw || \strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $secret);

        return false === $plain ? null : $plain;
    }

    public static function isEncrypted(?string $value): bool
    {
        return null !== $value && str_starts_with($value, self::PREFIX);
    }

    private function secret(): ?string
    {
        if (!$this->checked) {
            $this->checked = true;
            $raw = null !== $this->key ? base64_decode(trim($this->key), true) : false;
            $this->secret = false !== $raw && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === \strlen($raw) ? $raw : null;
        }

        return $this->secret;
    }
}
