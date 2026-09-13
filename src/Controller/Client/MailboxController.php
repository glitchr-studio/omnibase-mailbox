<?php

namespace Base\Mailbox\Controller\Client;

use App\Entity\User;
use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Form\Model\ComposeModel;
use Base\Mailbox\Form\Type\ComposeType;
use Base\Mailbox\Form\Type\ReplyType;
use Base\Mailbox\Repository\ConversationRepository;
use Base\Mailbox\Service\Mailbox;
use Base\Mailbox\Service\MailboxException;
use Base\Service\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    ) {
        $this->conversations = $entityManager->getRepository(Conversation::class);
    }

    #[Route('/messagerie/{box}', name: 'mailbox_index', defaults: ['box' => 'inbox'], requirements: ['box' => 'inbox|archive|sent'])]
    public function Index(Request $request, string $box = ConversationRepository::BOX_INBOX): Response
    {
        $this->denyAccessUnlessGranted($this->requiredRole);
        $user = $this->getUser();

        $conversations = $this->paginator->paginate($this->conversations->createBoxQuery($user, $box), $request->query->getInt('page', 1), $this->perPage);

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

        $form = $this->createForm(ComposeType::class, $model, ['subject_max_length' => $this->mailbox->getSubjectMaxLength()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $conversation = $this->mailbox->compose($this->getUser(), $model->getRecipientList(), (string) $model->subject, (string) $model->content);
                $names = implode(', ', array_map(fn (User $u) => (string) $u->getUsername(), $conversation->getOthers($this->getUser())));
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

        $conversation = $this->conversations->findOneForUser($id, $user);
        if (!$conversation) {
            throw $this->createNotFoundException('Unknown conversation.');
        }

        $form = $this->createForm(ReplyType::class, null, [
            'action' => $this->generateUrl('mailbox_reply', ['id' => $conversation->getId()]),
        ]);
        $form->handleRequest($request);

        $this->mailbox->markRead($user, $conversation);

        return $this->render('@Mailbox/client/show.html.twig', [
            'conversation' => $conversation,
            'me' => $conversation->getParticipant($user),
            'form' => $form->createView(),
            'unread' => $this->mailbox->countUnread($user),
        ]);
    }

    #[Route('/messagerie/{id}/repondre', name: 'mailbox_reply', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function Reply(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted($this->requiredRole);
        $user = $this->getUser();

        $conversation = $this->conversations->findOneForUser($id, $user);
        if (!$conversation) {
            throw $this->createNotFoundException('Unknown conversation.');
        }

        $form = $this->createForm(ReplyType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->mailbox->reply($user, $conversation, (string) $form->get('content')->getData());
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
