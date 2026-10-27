---
title: The mailbox
order: 30
---

# The mailbox

`Base\Mailbox\Controller\Client\MailboxController` turns the rules of `Base\Mailbox\Service\Mailbox`
into pages; a refusal is a `MailboxException` whose message is a translation key of the `mailbox`
domain, turned into a flash.

## The pages

- **A box** (`/messagerie/{box}`): the conversations, `mailbox.per_page` a page (`?page=`), the
  unread count, the inbox's count and its limit. `desk` lists the conversations written to the
  desks the member answers at (a 404 for a member who answers at none).
- **Compose** (`/messagerie/nouveau/{to?}`): `to` fills the recipients (typed usernames, or a
  directory choice); `?subject=` pre-titles it, cut to `subject_max_length` (a profile or a forum
  post opens a titled message). Sent, the page opens the conversation with "sent to ...".
- **A conversation** (`/messagerie/{id}`): its messages, the reply form, marked read on opening.
  A member reads a conversation they take part in and have not deleted, or one written to a desk
  they answer at; anything else is a 404. With nobody left to answer (and no desk) there is no
  reply form.
- **Its state** (`/messagerie/{id}/etat`): `{"latest": <message count>}`, `no-store` - what the
  live refresh asks.

## Composing

`Mailbox::compose(User $sender, array $usernames, string $subject, string $content): Conversation`,
in order:

| Check | Refusal key |
|---|---|
| subject and text not empty (trimmed) | `error.empty` |
| subject at most `subject_max_length` characters | `error.subject_too_long` (`%max%`) |
| text at most `content_max_length` | `error.content_too_long` (`%max%`) |
| at least one username, at most `max_recipients` | `error.no_recipient`, `error.too_many_recipients` (`%max%`) |
| each username exists (case aside) and is not the sender | `error.unknown_recipient` (`%username%`), `error.self` |
| `flood_interval` seconds since the sender's last message (not for `ROLE_ADMIN`; `0` off) | `error.flood` (`%seconds%` left) |
| each recipient's inbox below `inbox_limit` (`0` off) | `error.inbox_full` (`%username%`) |

The form splits the recipients on commas, semicolons and spaces. `composeTo(User $sender, array
$recipients, ...)` does the same with accounts already known (a directory's choice);
`composeToDesk(User $sender, string $desk, ...)` writes to a desk (`error.unknown_desk`).

## Replying

`Mailbox::reply(User $sender, Conversation $conversation, string $content): Message`: the text
checked as above; a desk's staff join the conversation by answering; refused when the sender is
not a participant or deleted it (`error.not_yours`), when nobody else is left (`error.nobody_left`,
outside desks), or for flooding. A reply restores the conversation for everyone who had deleted
it, as mail does. With attachments on, one file goes with it.

## Per member

`POST /messagerie/{id}/{action}` with the token `mailbox_<id>`: `archive` (then the archive box),
`unarchive`, `star`, `unstar`, `delete` (the member's row; the conversation goes when nobody keeps
it). `POST /messagerie/lot/{archive|delete}` with `ids[]` and the token `mailbox_batch`: several at
once from a list, back to the box it came from (`box`).

## Twig

`Base\Mailbox\Twig\MailboxTwigExtension`:

| | |
|---|---|
| `mailbox_unread_count()` | the signed-in member's unread conversations, counted once per request; `null` signed out or when the count fails (a menu never fails) |
| `mailbox_desks()` | the desks the member answers at, name => label |
| `mailbox_desk_waiting()` | how many of their desk conversations wait for an answer |
| `x\|mailbox_text` | a message as HTML: escaped, decrypted, paragraphs and line breaks kept, URLs made links (`rel="nofollow noopener"`, a new tab) |
| `x\|mailbox_plain` | a subject or a message as written; `…` when it cannot be decrypted |

Translations: the domain `mailbox`, ICU, in English and French (with polite variants of the French
texts: no "tu").
