---
title: Conversations and messages
order: 20
---

# Conversations and messages

## `Conversation`

`Base\Mailbox\Entity\Conversation` (`mailbox_conversation`):

| Property | Column | Meaning |
|---|---|---|
| `subject` | `string(255)` | sealed when `mailbox.encrypt` is on |
| `participants` | one-to-many `Participant`, cascade, orphan removal | |
| `messages` | one-to-many `Message`, cascade, orphan removal, oldest first | |
| `createdAt` | `datetime` | |
| `lastMessageAt` | `datetime` | the boxes sort on it |
| `lastSender` | many-to-one user, `SET NULL` | |
| `messageCount` | `integer` | what the live refresh compares |

`addParticipant(User)` (the existing row when there is one), `getParticipant(User)`,
`hasParticipant()`, `getOthers(?User $except)`, `getLastMessage()`, `isUnreadFor(User)` (something
arrived after they last read, and they did not write it). `addMessage(Message)` updates the count,
the date and the last sender, marks the sender's row read and un-archived, and un-archives the
others': a reply brings the conversation back into everyone's inbox.

## `Message`

`mailbox_message`, indexed on `createdAt`: `conversation` (`CASCADE`), `sender` (`SET NULL`),
`content` (`text`, not blank; plain text, line breaks kept, sealed when encrypted), `createdAt`.

## `Participant`

One member's view of a conversation (`mailbox_participant`, unique on conversation and user,
indexed on user, deleted, archived): `lastReadAt`, `archivedAt`, `deletedAt`, `starred`.
`markRead()`, `archive()`, `unarchive()`, `delete()`, `restore()`, `setStarred()`. One member
deleting a conversation never removes it from the other's box.

## The boxes

`ConversationRepository::createBoxQuery(User $user, string $box)` - the member's rows not deleted,
newest message first:

| Box | Holds |
|---|---|
| `inbox` (`BOX_INBOX`) | not archived |
| `archive` (`BOX_ARCHIVE`) | archived |
| `sent` (`BOX_SENT`) | not archived, and the member wrote last |

`countInbox(User)` (the quota: not deleted, not archived), `countUnread(User)` (not deleted,
someone else wrote last, read before the last message or never), `findOneForUser(int $id, User)`
(a conversation the member takes part in and has not deleted, with its participants and
messages). `MessageRepository::findLastBySender(User)` - the flood check.

## The optional entities

`Extension\Entity\DeskConversation` (`mailbox_desk_conversation`) and
`Extension\Entity\Attachment` (`mailbox_attachment`) are mapped only when `desks` or
`attachments` is configured ([Desks, notices, attachments](desks-attachments-encryption.md)):
a conversation written to a desk (`desk`, `author`; `isWaiting()` - its author wrote last), and a
message's file (the vault document's id, whether it is a picture).

`Event\MessageSentEvent` - `conversation`, `message`, `sender`, `desk` (the desk it was written
to), `first` (a new conversation's first message) - is dispatched for every message.
