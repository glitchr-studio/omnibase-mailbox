# omnibase/mailbox

Private messaging for the sites built on [omnisense/omnibase](https://github.com/glitchr-omnisense/omnibase):
member-to-member conversations with an inbox, sent and archive boxes, each member's own read,
star, archive and delete state, a box quota and flood control - and, each behind its option,
desks ("the secretariat"), a directory of recipients, an e-mail that says a message awaits, a
live refresh, attachments kept in omnibase/office's encrypted vault, and messages stored
encrypted. It is a Symfony bundle (`Base\Mailbox\MailboxBundle`) on the core 3 or 4.

Three entities: `Conversation` (a subject, its messages), `Message` (a sender, plain text) and
`Participant` (one row per member and conversation). A private message is deliberately not a
`Thread`: it is mail, not published content. Deleting is per member; the conversation itself is
removed once nobody keeps it. The limits are the historical site's: 100 conversations in a box,
two minutes between two messages, a 55-character subject.

## How this manual is organised

- [Installation](installation.md): requirements, the bundle, the routes, the schema, the layout.
- [Conversations and messages](concepts.md): the entities, the boxes, the queries.
- [The mailbox](usage-mailbox.md): the pages, composing, replying, the rules and their refusals,
  the per-member actions, the Twig functions.
- [Desks, notices, attachments, live refresh, encryption](desks-attachments-encryption.md): the
  options that are off by default.
- [Configuration reference](configuration.md): `mailbox:`, the services.
- [Testing](testing.md).
