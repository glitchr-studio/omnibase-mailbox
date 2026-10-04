---
title: Desks, notices, attachments, encryption
order: 2
---

# Desks, notices, attachments, live refresh, encryption

Six options, **all off by default**: a site that sets none of them runs as before, on the same three
tables, with the same service and the same pages.

```yaml
# config/packages/mailbox.yaml
mailbox:
    desks:                                   # adds the table mailbox_desk_conversation
        secretariat: ROLE_SECRETARY          # or { role: ROLE_SECRETARY, label: Secrétariat }
    directory: true                          # compose to a choice instead of typed usernames
    notify: true                             # e-mail the others that a message awaits - never its content
    sender: '%env(MAILER_TECHNICAL)%'
    poll: 10                                 # seconds between two checks of the open conversation
    attachments: true                        # needs omnibase/office; adds the table mailbox_attachment
    encrypt: true
    key: '%env(default::MAILBOX_KEY)%'       # base64 of 32 bytes, from the secrets vault
```

The two tables are mapped only when `desks` or `attachments` is configured
(`MailboxExtension::prepend()`): otherwise there is nothing to migrate.

## Desks

A member writes to "the secretariat" without knowing anyone's username:
`Mailbox::composeToDesk($sender, 'secretariat', $subject, $content)`. The author is the
conversation's only participant at first. Whoever holds the desk's role - their own, a group's, or
through the role hierarchy - finds it in the box `/messagerie/desk`, reads it, and joins it by
answering. Twig: `mailbox_desks()`, `mailbox_desk_waiting()`.

## Directory

With `directory: true` the compose form is a choice: the desks, then whoever the site's
`Base\Mailbox\Recipient\RecipientProviderInterface` services offer this sender (a label => the
account). What comes back from the form is looked up again in that list, never trusted.
`Mailbox::composeTo($sender, [$user], …)` writes to accounts already known.

## Notices

`notify: true`: each other participant who kept the conversation is mailed "a message awaits you"
with a link to it - not its text, not its subject, not who wrote. `MessageSentEvent` is dispatched
for every message whatever the option: listen to it for a push.

## Live refresh

`poll: 10`: the open conversation carries omnibase's Stimulus `poll` controller (register it in the
application's `assets/bootstrap.js`), which asks `mailbox_state` (`/messagerie/{id}/etat`,
`{"latest": <count>}`); on a change `mailbox.js` loads the new messages in place.

## Attachments

`attachments: true` with omnibase/office installed: a file with a reply, kept in the office's
encrypted vault as a document of the conversation (`context: mailbox:<id>`), read by the
conversation's participants and the desk's staff (`ConversationAudience`), logged like any other
document. Pictures are shown in the conversation.

## Encryption

`encrypt: true`: subjects and messages are stored sealed (libsodium `crypto_secretbox`), as
`mbx1:` + base64 in the same columns. What was written before stays readable. Read them through
`|mailbox_plain` and `|mailbox_text`, or `Mailbox::reveal()`.

**Fail closed**: `encrypt: true` and no usable key, `compose()` and `reply()` throw
`MailboxException('error.no_key')` and nothing is stored.

```sh
php -r 'echo base64_encode(random_bytes(32));' | bin/console secrets:set MAILBOX_KEY -
```

## Compatibility

- `Mailbox`'s new constructor arguments (`$cipher`, `$desks`, `$dispatcher`) are last and optional.
- `compose()`, `reply()` and every existing route, template block and translation key are unchanged.
- `ComposeModel::$recipients` is validated as before when `directory` is off.
