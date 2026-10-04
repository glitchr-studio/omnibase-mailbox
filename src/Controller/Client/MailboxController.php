<?php

namespace Base\Mailbox\Controller\Client;

use App\Entity\User;
use Base\Mailbox\Attachment\Office\VaultAttachments;
use Base\Mailbox\Desk\Desks;
use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Form\Model\ComposeModel;
use Base\Mailbox\Form\Type\ComposeType;
use Base\Mailbox\Form\Type\ReplyType;
use Base\Mailbox\Recipient\Directory;
use Base\Mailbox\Repository\ConversationRepository;
use Base\Mailbox\Service\Mailbox;
use Base\Mailbox\Service\MailboxException;
use Base\Service\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The member's mailbox: inbox, archives, a conversation, composing and
 * replying. Everything is behind ROLE_USER (mailbox.required_role is
 * checked on top in each action).
 *
 * Route names are `mailbox_*`; the paths are French, as the site is.
 */
#[IsGranted('ROLE_USER')]
class MailboxController extends AbstractController
{
    private readonly ConversationRepository $conversations;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly Mailbox $mailbox,
        private readonly PaginatorInterface $paginator,
        private readonly TranslatorInterface $translator,
        #[Autowire('%mailbox.per_page%')] private readonly int $perPage = 20,
        #[Autowire('%mailbox.required_role%')] private readonly string $requiredRole = 'ROLE_USER',
        // What follows is off unless configured (mailbox.desks, directory, poll, attachments).
        private readonly ?Desks $desks = null,
        private readonly ?Directory $directory = null,
        #[Autowire('%mailbox.directory%')] private readonly bool $useDirectory = false,
        #[Autowire('%mailbox.poll%')] private readonly int $poll = 0,
        #[Autowire(service: 'mailbox.attachments')] private readonly ?object $attachments = null,
    ) {
        $this->conversations = $entityManager->getRepository(Conversation::class);
    }

    #[Route('/messagerie/{box}', name: 'mailbox_index', defaults: ['box' => 'inbox'], requirements: ['box' => 'inbox|archive|sent|desk'])]
    public function Index(Request $request, string $box = ConversationRepository::BOX_INBOX): Response
    {
        $this->denyAccessUnlessGranted($this->requiredRole);
        $user = $this->getUser();

        if ('desk' === $box) {
            // The conversations written to the desks this member answers at.
            $mine = $this->desks?->forUser($user) ?? [];
            if ([] === $mine) {
                throw $this->createNotFoundException('No desk.');
            }
            $query = $this->desks->conversations()->createDeskQuery($mine);
        } else {
            $query = $this->conversations->createBoxQuery($user, $box);
        }
        $conversations = $this->paginator->paginate($query, $request->query->getInt('page', 1), $this->perPage);

        return $this->render('@Mailbox/client/index.html.twig', [
            'box' => $box,
            'conversations' => $conversations,
            'unread' => $this->mailbox->countUnread($user),
            'count' => $this->conversations->countInbox($user),
            'limit' => $this->mailbox->getInboxLimit(),
        ]);
    }

    #[Route('/messagerie/nouveau/{to}', name: 'mailbox_compose', defaults: ['to' => null], priority: 5)]
    public function Compose(Request $request, ?string $to = null): Response
    {
        $this->denyAccessUnlessGranted($this->requiredRole);

        $model = new ComposeModel();
        $model->recipients = $to;
        // "?subject=" lets a profile or a forum post open a pre-titled message.
        $model->subject = mb_substr((string) $request->query->get('subject', ''), 0, $this->mailbox->getSubjectMaxLength()) ?: null;

        // With mailbox.directory, a choice (the desks, the people the site offers) replaces the typed usernames.
        $choices = $this->useDirectory && null !== $this->directory ? $this->directory->choices($this->getUser()) : null;
        if (null !== $choices && null !== $to && \in_array($to, $choices, true)) {
            $model->to = $to;
        }
        $form = $this->createForm(ComposeType::class, $model, ['subject_max_length' => $this->mailbox->getSubjectMaxLength(), 'directory' => $choices]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (null !== $choices) {
                    $target = $this->directory->resolve($this->getUser(), $model->to);
                    if (null === $target) {
                        throw new MailboxException('error.no_recipient');
                    }
                    $conversation = \is_string($target)
                        ? $this->mailbox->composeToDesk($this->getUser(), $target, (string) $model->subject, (string) $model->content)
                        : $this->mailbox->composeTo($this->getUser(), [$target], (string) $model->subject, (string) $model->content);
                    $names = \is_string($target) ? $this->desks->label($target) : array_search($model->to, $choices, true);
                } else {
                    $conversation = $this->mailbox->compose($this->getUser(), $model->getRecipientList(), (string) $model->subject, (string) $model->content);
                    $names = implode(', ', array_map(fn (User $u) => (string) $u->getUsername(), $conversation->getOthers($this->getUser())));
                }
                $this->addFlash('success', $this->translator->trans('@mailbox.flash.sent', ['%username%' => $names]));

                return $this->redirectToRoute('mailbox_show', ['id' => $conversation->getId()]);
            } catch (MailboxException $e) {
                $this->addFlash('error', $this->translator->trans('@mailbox.' . $e->getMessage(), $e->getParameters()));
            }
        }

        return $this->render('@Mailbox/client/compose.html.twig', [
            'form' => $form->createView(),
            'unread' => $this->mailbox->countUnread($this->getUser()),
        ]);
    }

    #[Route('/messagerie/{id}', name: 'mailbox_show', requirements: ['id' => '\d+'])]
    public function Show(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted($this->requiredRole);
        $user = $this->getUser();

        $conversation = $this->find($id, $user);

        $form = $this->createForm(ReplyType::class, null, [
            'action' => $this->generateUrl('mailbox_reply', ['id' => $conversation->getId()]),
            'attachments' => $this->hasAttachments(),
        ]);
        $form->handleRequest($request);

        $this->mailbox->markRead($user, $conversation);
        $desk = $this->desks?->of($conversation);

        return $this->render('@Mailbox/client/show.html.twig', [
            'conversation' => $conversation,
            'me' => $conversation->getParticipant($user),
            'form' => $form->createView(),
            'unread' => $this->mailbox->countUnread($user),
            'desk' => $desk,
            'desk_label' => $desk ? $this->desks->label($desk->getDesk()) : null,
            'can_reply' => null !== $desk || [] !== $conversation->getOthers($user),
            'attachments' => $this->hasAttachments() ? $this->attachments->of($conversation) : [],
            'poll' => $this->poll,
        ]);
    }

    /** What the open conversation's page asks every mailbox.poll seconds: has something arrived? */
    #[Route('/messagerie/{id}/etat', name: 'mailbox_state', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function State(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->requiredRole);
        $conversation = $this->find($id, $this->getUser());

        return new JsonResponse(['latest' => $conversation->getMessageCount()], 200, ['Cache-Control' => 'no-store']);
    }

    /** A conversation the member takes part in, or one written to a desk they answer at. */
    private function find(int $id, User $user): Conversation
    {
        $conversation = $this->conversations->findOneForUser($id, $user);
        if (!$conversation && null !== $this->desks && $this->desks->isEnabled()) {
            $candidate = $this->conversations->find($id);
            if ($candidate && $this->desks->staffs($user, $candidate)) {
                $conversation = $candidate;
            }
        }

        return $conversation ?? throw $this->createNotFoundException('Unknown conversation.');
    }

    private function hasAttachments(): bool
    {
        return $this->attachments instanceof VaultAttachments && $this->attachments->isEnabled();
    }

    #[Route('/messagerie/{id}/repondre', name: 'mailbox_reply', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function Reply(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted($this->requiredRole);
        $user = $this->getUser();

        $conversation = $this->find($id, $user);

        $form = $this->createForm(ReplyType::class, null, ['attachments' => $this->hasAttachments()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $message = $this->mailbox->reply($user, $conversation, (string) $form->get('content')->getData());
                $file = $this->hasAttachments() ? $form->get('attachment')->getData() : null;
                if ($file instanceof UploadedFile) {
                    // Whose document it is: the other participant's; written to a desk, its author's.
                    $others = array_values(array_filter($conversation->getOthers($user)));
                    $owner = $this->desks?->of($conversation)?->getAuthor() ?? (1 === \count($others) ? $others[0] : $user);
                    $this->attachments->attach($message, $file, $user, $owner);
                }
                $this->addFlash('success', $this->translator->trans('@mailbox.flash.replied'));
            } catch (MailboxException $e) {
                $this->addFlash('error', $this->translator->trans('@mailbox.' . $e->getMessage(), $e->getParameters()));
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('mailbox_show', ['id' => $conversation->getId()]);
    }

    /** archive / unarchive / star / unstar / delete - one POST, one switch, CSRF in the form. */
    #[Route('/messagerie/{id}/{action}', name: 'mailbox_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'archive|unarchive|star|unstar|delete'])]
    public function Action(Request $request, int $id, string $action): Response
    {
        $this->denyAccessUnlessGranted($this->requiredRole);
        $user = $this->getUser();

        $conversation = $this->conversations->findOneForUser($id, $user);
        if (!$conversation) {
            throw $this->createNotFoundException('Unknown conversation.');
        }
        if (!$this->isCsrfTokenValid('mailbox_' . $conversation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid token.');
        }

        switch ($action) {
            case 'archive':
                $this->mailbox->archive($user, $conversation, true);
                $this->addFlash('success', $this->translator->trans('@mailbox.flash.archived'));
                return $this->redirectToRoute('mailbox_index', ['box' => 'archive']);
            case 'unarchive':
                $this->mailbox->archive($user, $conversation, false);
                $this->addFlash('success', $this->translator->trans('@mailbox.flash.unarchived'));
                return $this->redirectToRoute('mailbox_index');
            case 'star':
            case 'unstar':
                $this->mailbox->star($user, $conversation, 'star' === $action);
                return $this->redirectToRoute('mailbox_show', ['id' => $conversation->getId()]);
            case 'delete':
                $this->mailbox->delete($user, $conversation);
                $this->addFlash('success', $this->translator->trans('@mailbox.flash.deleted'));
                return $this->redirectToRoute('mailbox_index');
        }

        return $this->redirectToRoute('mailbox_index');
    }

    /** Several at once from the list: the old inbox's row of checkboxes. */
    #[Route('/messagerie/lot/{action}', name: 'mailbox_batch', methods: ['POST'], requirements: ['action' => 'archive|delete'], priority: 5)]
    public function Batch(Request $request, string $action): Response
    {
        $this->denyAccessUnlessGranted($this->requiredRole);
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('mailbox_batch', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid token.');
        }

        $done = 0;
        foreach ((array) $request->request->all('ids') as $id) {
            $conversation = $this->conversations->findOneForUser((int) $id, $user);
            if (!$conversation) {
                continue;
            }
            'delete' === $action ? $this->mailbox->delete($user, $conversation) : $this->mailbox->archive($user, $conversation, true);
            ++$done;
        }

        $this->addFlash('success', $this->translator->trans('delete' === $action ? '@mailbox.flash.batch_deleted' : '@mailbox.flash.batch_archived', ['%count%' => $done]));

        return $this->redirectToRoute('mailbox_index', ['box' => (string) $request->request->get('box', 'inbox')]);
    }
}
