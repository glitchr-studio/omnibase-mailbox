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

## Try it in one command

A self-contained demo (a bare Symfony skeleton, base-bundle, this checkout and SQLite) ships in the root [Dockerfile](Dockerfile). Its first page seeds two members and a conversation between them, signs you in as one of them, and walks the rules (what is refused and why); *switch member* in the top bar swaps you to the other side of the conversation:

```bash
docker build -t base-bundle-mailbox-demo .
docker run --rm -p 8000:8000 base-bundle-mailbox-demo
# → http://localhost:8000/            the tour
# → http://localhost:8000/messagerie  the mailbox
```

The demo app under [example/app/](example/app/) doubles as the minimal host: the `bundles.php`, `routes.yaml`, `doctrine.yaml`, `security.yaml` and `mailbox.yaml` an application needs, and a `layout1.html.twig` showing the only contract the mailbox's templates have with their host — `content`, `aside`, `title`, `stylesheets` and `javascripts` blocks.

## Development

```bash
make tests                               # phpunit, standalone or from inside a host app
docker compose run --rm test             # the same, in a clean php:8.4 container
docker compose run --rm test composer test-coverage   # → var/coverage/index.html
```

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
