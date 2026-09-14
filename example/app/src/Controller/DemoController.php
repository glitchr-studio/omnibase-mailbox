<?php

namespace App\Controller;

use App\Entity\User;
use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Service\Mailbox;
use Base\Mailbox\Service\MailboxException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The demonstrator: it seeds two members and a conversation between them,
 * signs you in as one, walks the mailbox's rules - then hands over to the
 * mailbox itself. "Switch member" swaps you to the other side of the
 * conversation, so both ends of an exchange can be seen in one browser.
 *
 * Demo scaffolding throughout: a real application signs members in through
 * base-bundle's security controllers.
 */
class DemoController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Mailbox $mailbox,
        private readonly Security $security,
    ) {
    }

    #[Route('/', name: 'demo_index')]
    public function index(): Response
    {
        $sections = [];
        $demo = function (string $title, string $about, callable $fn) use (&$sections) {
            try {
                $sections[] = ['title' => $title, 'about' => $about, 'ok' => true, 'output' => $fn()];
            } catch (\Throwable $e) {
                $sections[] = ['title' => $title, 'about' => $about, 'ok' => false, 'output' => $e::class.': '.$e->getMessage()];
            }
        };

        [$marki, $chimbo, $log] = $this->seed();
        $demo('Seed', 'Two members and one conversation between them - created once, found again on every reload.', fn () => $log);

        $demo('One conversation, two boxes', 'The conversation is shared; each member has their own Participant row - last read, starred, archived, deleted.', function () use ($marki, $chimbo) {
            $lines = [];
            foreach ([$marki, $chimbo] as $member) {
                $lines[] = sprintf('%-8s inbox: %d   unread: %d', $member->getUsername(), $this->conversations()->countInbox($member), $this->mailbox->countUnread($member));
            }

            return implode("\n", $lines);
        });

        $demo('The rules say no', 'Every refusal is a MailboxException carrying a translation key, turned into a flash by the controller.', function () use ($marki) {
            $lines = [];
            foreach ([
                'to nobody' => [[], 'Hello', 'Hi'],
                'to oneself' => [['Marki'], 'Hello', 'Hi'],
                'to a stranger' => [['Nobody'], 'Hello', 'Hi'],
                'empty body' => [['Chimbo'], 'Hello', '   '],
                'subject too long' => [['Chimbo'], str_repeat('x', 80), 'Hi'],
            ] as $label => [$to, $subject, $content]) {
                try {
                    $this->mailbox->compose($marki, $to, $subject, $content);
                    $lines[] = sprintf('%-18s accepted (!)', $label);
                } catch (MailboxException $e) {
                    $lines[] = sprintf('%-18s refused: %s %s', $label, $e->getMessage(), $e->getParameters() ? json_encode($e->getParameters()) : '');
                }
            }

            return implode("\n", $lines);
        });

        $demo('Signed in', 'The mailbox is behind ROLE_USER: the demo signs you in, and "switch member" in the bar swaps sides.', fn () => 'you are '.($this->getUser()?->getUserIdentifier() ?? 'nobody'));

        return $this->render('demo/index.html.twig', ['sections' => $sections]);
    }

    /** Swap to the other member, to read the conversation from the other side. */
    #[Route('/switch', name: 'demo_switch')]
    public function switch(): Response
    {
        // The identifier is the email; the demo's members are told apart by username.
        $next = 'Marki' === $this->getUser()?->getUsername() ? 'Chimbo' : 'Marki';
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $next]);
        if ($user) {
            $this->security->login($user, 'security.authenticator.form_login.main');
        }

        return $this->redirectToRoute('mailbox_index');
    }

    /** @return array{0: User, 1: User, 2: string} */
    private function seed(): array
    {
        $users = $this->entityManager->getRepository(User::class);
        $created = [];

        if (!$marki = $users->findOneBy(['username' => 'Marki'])) {
            $marki = $this->member('Marki', 'marki@example.org');
            $created[] = 'member Marki';
        }
        if (!$chimbo = $users->findOneBy(['username' => 'Chimbo'])) {
            $chimbo = $this->member('Chimbo', 'chimbo@example.org');
            $created[] = 'member Chimbo';
        }
        $this->entityManager->flush();

        if (!$this->entityManager->getRepository(Conversation::class)->findOneBy([])) {
            $conversation = $this->mailbox->compose($chimbo, ['Marki'], 'Bienvenue sur l\'Archipel', "Salut Marki !\n\nCe message a été envoyé par le démonstrateur. Réponds-moi depuis ta boîte : https://example.org");
            $this->mailbox->reply($marki, $conversation, 'Merci Chimbo, bien reçu !');
            $this->mailbox->reply($chimbo, $conversation, 'Et une troisième ligne, pour que la conversation ait de quoi défiler.');
            $created[] = 'conversation "Bienvenue sur l\'Archipel" with three messages';
        }

        if (!$this->getUser()) {
            $this->security->login($marki, 'security.authenticator.form_login.main');
        }

        return [$marki, $chimbo, $created ? "created:\n  - ".implode("\n  - ", $created) : 'nothing to do: already seeded.'];
    }

    private function member(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPlainPassword('demo');
        $user->setRoles(['ROLE_USER']);
        $user->verify();
        $this->entityManager->persist($user);

        return $user;
    }

    private function conversations(): \Base\Mailbox\Repository\ConversationRepository
    {
        return $this->entityManager->getRepository(Conversation::class);
    }
}
