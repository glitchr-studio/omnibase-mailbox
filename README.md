# Mailbox

Private messaging for [base-bundle](https://gitlab.glitchr.dev/public-repository/symfony/bundle/base/component):
member-to-member conversations with an inbox, sent and archive boxes, per-member
read/star/delete state, a box quota and flood control.

Three entities: `Conversation` (subject, messages), `Message` (sender, plain
text) and `Participant` (one row per member and conversation: last read,
archived, starred, deleted). Deleting is per member; the conversation itself is
removed once nobody keeps it.

## Install

```bash
composer require glitchr/base-bundle-mailbox:dev-main
```

```php
// config/bundles.php
Base\Mailbox\MailboxBundle::class => ['all' => true],
```

```yaml
# config/routes.yaml
mailbox_controller:
    resource: "@MailboxBundle/src/Controller/Client"
    type: attribute
    prefix: /
```

```yaml
# config/packages/mailbox.yaml (every key optional)
mailbox:
    per_page: 20
    inbox_limit: 100          # 0 = unlimited
    flood_interval: 120       # seconds between two messages
    subject_max_length: 55
    max_recipients: 5
    required_role: ROLE_USER
```

Then a migration, and `bin/console assets:install` for `public/css/mailbox.css`.

## Routes

| name | path |
|---|---|
| `mailbox_index` | `/messagerie/{inbox,sent,archive}` |
| `mailbox_compose` | `/messagerie/nouveau/{to?}?subject=` |
| `mailbox_show` | `/messagerie/{id}` |
| `mailbox_reply` | `POST /messagerie/{id}/repondre` |
| `mailbox_action` | `POST /messagerie/{id}/{archive,unarchive,star,unstar,delete}` |
| `mailbox_batch` | `POST /messagerie/lot/{archive,delete}` |

## Twig

- `mailbox_unread_count()` — unread conversations of the signed-in member (null when signed out), for a menu badge.
- `text|mailbox_text` — a message body as safe HTML.

## Override points

`templates/bundles/MailboxBundle/client/_banner.html.twig` and `_avatar.html.twig`.
