---
title: Configuration reference
order: 40
---

# Configuration reference

## `mailbox:`

`Base\Mailbox\DependencyInjection\MailboxConfiguration`, dumped with Symfony's
`YamlReferenceDumper` in omnisense/omnibase's harness image:

```yaml
mailbox:

    # Conversations listed per page.
    per_page:             20

    # Conversations a member may keep in the inbox; 0 for no limit. The old site refused mail to a full box.
    inbox_limit:          100

    # Seconds between two messages sent by the same member.
    flood_interval:       120
    subject_max_length:   55
    content_max_length:   10000

    # How many members one conversation may be started with.
    max_recipients:       5

    # Role needed to use the mailbox at all.
    required_role:        ROLE_USER

    # Desks a member writes to without knowing anyone's username ("the secretariat"): a name => the role whose holders read and answer it. Adds the table mailbox_desk_conversation.
    desks:

        # Prototype
        name:
            role:                 ~ # Required

            # How it is shown; null: its name.
            label:                null

    # Compose to a choice (the desks, and whoever the RecipientProviderInterface services offer) instead of typed usernames.
    directory:            false

    # E-mail the other participants that a message awaits - never its content, nor its subject.
    notify:               false

    # The From of those e-mails.
    sender:               '%env(default::MAILER_TECHNICAL)%'

    # Seconds between two checks for new messages in the open conversation (omnibase's Stimulus poll controller); 0: none.
    poll:                 0

    # A file with a reply, kept in omnibase/office's encrypted vault (needs omnibase/office). Adds the table mailbox_attachment.
    attachments:          false

    # Subjects and messages stored encrypted (libsodium). Fail closed: with no usable key, nothing is sent.
    encrypt:              false

    # Base64 of 32 bytes, from the secrets vault (MAILBOX_KEY).
    key:                  null
```

| Option | Type | Default |
|---|---|---|
| `per_page` | int, 1 or more | `20` |
| `inbox_limit` | int, 0 or more | `100` |
| `flood_interval` | int, 0 or more | `120` |
| `subject_max_length` | int, 10 or more | `55` |
| `content_max_length` | int, 100 or more | `10000` |
| `max_recipients` | int, 1 or more | `5` |
| `required_role` | role | `ROLE_USER` |
| `desks` | name => role, or name => `{role, label}` | `{}` |
| `directory`, `notify`, `attachments`, `encrypt` | bool | `false` |
| `sender` | address | `%env(default::MAILER_TECHNICAL)%` |
| `poll` | int, 0 or more | `0` |
| `key` | base64 of 32 bytes, or null | `null` |

> [!WARNING]
> Known defect: the compose form's model (`ComposeModel::$subject`) is validated at 55 characters
> whatever `subject_max_length` says, where the option is meant to set the limit: raised above 55,
> longer subjects are still refused by the form.

`MailboxExtension::prepend()` maps the extension entities (`Base\Mailbox\Extension\Entity`, alias
`MailboxExtension`) into Doctrine only when `desks` or `attachments` is configured.

## Services

`config/services.php` autowires and autoconfigures `src/` but `DependencyInjection/`, `Entity/`,
the bundle class, `Service/*Exception.php`, `Event/`, `Extension/Entity/` and
`Attachment/Office/`; `Controller/` is tagged `controller.service_arguments`. With
omnibase/office's `AudienceResolverInterface`, `Attachment/Office/` is loaded and
`mailbox.attachments` is an alias of `VaultAttachments`; without it, `mailbox.attachments` is a
`stdClass` (no attachments). `RecipientProviderInterface` is autoconfigured with the tag
`mailbox.recipient_provider`; `NotifyListener` listens to `MessageSentEvent`.
