<?php

namespace Tests\Base\Mailbox\DependencyInjection;

use Base\Mailbox\DependencyInjection\MailboxConfiguration;
use Base\Mailbox\DependencyInjection\MailboxExtension;
use Base\Mailbox\Form\Model\ComposeModel;
use Base\Mailbox\Service\Mailbox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A site that configures none of the new options runs as before: every one
 * is off, no table is added to its schema, and the service is built with
 * the arguments it always had.
 */
final class BackwardCompatibilityTest extends TestCase
{
    public function testEveryNewOptionIsOffByDefault(): void
    {
        $config = (new Processor())->processConfiguration(new MailboxConfiguration(), [[]]);

        self::assertSame([], $config['desks']);
        self::assertFalse($config['directory']);
        self::assertFalse($config['notify']);
        self::assertSame(0, $config['poll']);
        self::assertFalse($config['attachments']);
        self::assertFalse($config['encrypt']);
        self::assertNull($config['key']);
        // And what was there is unchanged.
        self::assertSame([20, 100, 120, 55, 10000, 5, 'ROLE_USER'], [$config['per_page'], $config['inbox_limit'], $config['flood_interval'], $config['subject_max_length'], $config['content_max_length'], $config['max_recipients'], $config['required_role']]);
    }

    public function testNoTableIsAddedUnlessDesksOrAttachmentsAreConfigured(): void
    {
        foreach ([[[]], [['notify' => true, 'encrypt' => true, 'poll' => 10]]] as $configs) {
            $container = $this->prepend($configs);
            self::assertSame([], $container->getExtensionConfig('doctrine'), 'the extension\'s entities are not mapped');
        }
        foreach ([[['desks' => ['secretariat' => 'ROLE_SECRETARY']]], [['attachments' => true]]] as $configs) {
            $mappings = $this->prepend($configs)->getExtensionConfig('doctrine')[0]['orm']['mappings'];
            self::assertSame('Base\Mailbox\Extension\Entity', $mappings['MailboxExtension']['prefix']);
        }
    }

    public function testADeskIsARoleOrARoleAndALabel(): void
    {
        $config = (new Processor())->processConfiguration(new MailboxConfiguration(), [['desks' => ['accueil' => 'ROLE_STAFF', 'secretariat' => ['role' => 'ROLE_SECRETARY', 'label' => 'Secrétariat']]]]);

        self::assertSame(['role' => 'ROLE_STAFF', 'label' => null], $config['desks']['accueil']);
        self::assertSame('Secrétariat', $config['desks']['secretariat']['label']);
    }

    public function testTheServicesNewArgumentsAreLastAndOptional(): void
    {
        $parameters = (new \ReflectionClass(Mailbox::class))->getConstructor()->getParameters();
        $names = array_map(static fn (\ReflectionParameter $p) => $p->getName(), $parameters);

        self::assertSame(['entityManager', 'security', 'inboxLimit', 'floodInterval', 'subjectMaxLength', 'contentMaxLength', 'maxRecipients'], \array_slice($names, 0, 7), 'as they were');
        foreach (\array_slice($parameters, 7) as $added) {
            self::assertTrue($added->isDefaultValueAvailable() && null === $added->getDefaultValue(), $added->getName().' is optional');
        }
        self::assertTrue((new \ReflectionMethod(Mailbox::class, 'compose'))->isPublic());
        self::assertSame(['Chimbo', 'Marki'], (static function () { $m = new ComposeModel(); $m->recipients = 'Chimbo, Marki'; return $m->getRecipientList(); })());
    }

    private function prepend(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends \Symfony\Component\DependencyInjection\Extension\Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $extension = new MailboxExtension();
        $container->registerExtension($extension);
        foreach ($configs as $config) {
            $container->loadFromExtension($extension->getAlias(), $config);
        }
        $extension->prepend($container);

        return $container;
    }
}
