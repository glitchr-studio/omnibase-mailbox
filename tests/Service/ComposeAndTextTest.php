<?php

namespace Tests\Base\Mailbox\Service;

use Base\Mailbox\Form\Model\ComposeModel;
use Base\Mailbox\Service\MailboxException;
use Base\Mailbox\Twig\MailboxTwigExtension;
use PHPUnit\Framework\TestCase;

/**
 * The two places where what a member types turns into something else: the
 * recipients field (a free-form list of usernames) and a message body
 * rendered as HTML for the other side.
 */
class ComposeAndTextTest extends TestCase
{
    public function testRecipientsSplitOnCommasSemicolonsAndSpaces(): void
    {
        $model = new ComposeModel();
        $model->recipients = " Chimbo, Marki;  Bloby  ,, ";

        $this->assertSame(['Chimbo', 'Marki', 'Bloby'], $model->getRecipientList());
    }

    public function testNoRecipientsIsAnEmptyList(): void
    {
        $this->assertSame([], (new ComposeModel())->getRecipientList());
    }

    public function testMessageTextIsEscapedAndKeepsItsParagraphs(): void
    {
        $extension = (new \ReflectionClass(MailboxTwigExtension::class))->newInstanceWithoutConstructor();
        $html = $extension->text("<b>not bold</b>\nsecond line\n\nnew paragraph");

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('<br />', $html);
        $this->assertSame(2, substr_count($html, '<p>'));
    }

    public function testLinksBecomeSafeAnchors(): void
    {
        $extension = (new \ReflectionClass(MailboxTwigExtension::class))->newInstanceWithoutConstructor();
        $html = $extension->text('see https://example.org/page, then reply');

        $this->assertStringContainsString('<a href="https://example.org/page" rel="nofollow noopener"', $html);
        $this->assertStringNotContainsString('page,"', $html);
    }

    public function testARefusalCarriesItsKeyAndParameters(): void
    {
        $e = new MailboxException('error.flood', ['%seconds%' => 42]);

        $this->assertSame('error.flood', $e->getMessage());
        $this->assertSame(['%seconds%' => 42], $e->getParameters());
    }
}
