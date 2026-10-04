<?php

namespace Tests\Base\Mailbox\Crypto;

use Base\Mailbox\Crypto\MessageCipher;
use Base\Mailbox\Service\MailboxException;
use PHPUnit\Framework\TestCase;

/** mailbox.encrypt: off, nothing changes; on, nothing is stored clear - nor at all without a key. */
final class MessageCipherTest extends TestCase
{
    public function testOffTheTextIsStoredAsItAlwaysWas(): void
    {
        $cipher = new MessageCipher();

        self::assertFalse($cipher->isEnabled());
        self::assertTrue($cipher->isReady());
        self::assertSame('Salut !', $cipher->encrypt('Salut !'));
        self::assertSame('Salut !', $cipher->decrypt('Salut !'));
        self::assertNull($cipher->decrypt(null));
    }

    public function testOnTheTextIsSealedAndComesBack(): void
    {
        $cipher = new MessageCipher(true, base64_encode(random_bytes(32)));
        $stored = $cipher->encrypt('Mon traitement me donne des vertiges.');

        self::assertStringStartsWith(MessageCipher::PREFIX, $stored);
        self::assertTrue(MessageCipher::isEncrypted($stored));
        self::assertStringNotContainsString('vertiges', $stored);
        self::assertNotSame($stored, $cipher->encrypt('Mon traitement me donne des vertiges.'), 'a nonce each time');
        self::assertSame('Mon traitement me donne des vertiges.', $cipher->decrypt($stored));
    }

    public function testWhatWasWrittenBeforeEncryptionStaysReadable(): void
    {
        $cipher = new MessageCipher(true, base64_encode(random_bytes(32)));

        self::assertSame('Un ancien message', $cipher->decrypt('Un ancien message'));
    }

    public function testWithoutAUsableKeyNothingIsSent(): void
    {
        foreach ([null, '', 'pas-du-base64', base64_encode('trop court')] as $key) {
            $cipher = new MessageCipher(true, $key);
            self::assertFalse($cipher->isReady());
            try {
                $cipher->encrypt('secret');
                self::fail('stored without a key');
            } catch (MailboxException $e) {
                self::assertSame('error.no_key', $e->getMessage());
            }
        }
    }

    public function testAnotherKeyOrAnAlteredTextIsNotRead(): void
    {
        $stored = (new MessageCipher(true, base64_encode(random_bytes(32))))->encrypt('secret');

        self::assertNull((new MessageCipher(true, base64_encode(random_bytes(32))))->decrypt($stored));
        self::assertNull((new MessageCipher(true, null))->decrypt($stored));
        self::assertNull((new MessageCipher(true, base64_encode(random_bytes(32))))->decrypt(MessageCipher::PREFIX.'AAAA'));
    }
}
