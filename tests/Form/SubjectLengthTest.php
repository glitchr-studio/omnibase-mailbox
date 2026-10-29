<?php

namespace Tests\Base\Mailbox\Form;

use Base\Mailbox\Form\Model\ComposeModel;
use Base\Mailbox\Form\Type\ComposeType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The compose form refuses a subject longer than its subject_max_length option
 * (mailbox.subject_max_length, given by MailboxController), and only then: ComposeModel capped
 * it at 55 whatever the option, so a site that raised it still had longer subjects refused.
 */
final class SubjectLengthTest extends KernelTestCase
{
    private function subjectErrors(int $max, int $length): int
    {
        $form = static::getContainer()->get('form.factory')->create(ComposeType::class, new ComposeModel(), ['subject_max_length' => $max, 'csrf_protection' => false]);
        $form->submit(['recipients' => 'someone', 'subject' => str_repeat('a', $length), 'content' => 'Hello']);

        return \count($form->get('subject')->getErrors(true));
    }

    public function testTheLongestSubjectIsTheOptions(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness).');
        }
        self::bootKernel();
        self::assertSame(0, $this->subjectErrors(120, 80), 'raised to 120: 80 characters pass');
        self::assertSame(1, $this->subjectErrors(120, 121));
        self::assertSame(1, $this->subjectErrors(55, 80), 'the default 55');
    }
}
