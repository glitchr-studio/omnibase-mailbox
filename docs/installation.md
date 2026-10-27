---
title: Installation
order: 10
---

# Installation

## Requirements

What `composer.json` requires: PHP 8.2 or later, `doctrine/orm` 2.15 or 3,
`omnisense/omnibase` 3.0 or 4.0, `omnibridge/symfony` 1.x, and `symfony/config`,
`dependency-injection`, `http-kernel`, `form` and `security-bundle` 6, 7 or 8. It suggests
`ext-sodium` (`mailbox.encrypt`), omnibase/office (`mailbox.attachments`) and `symfony/mailer`
(`mailbox.notify`). The lists are paginated by the core's paginator.

## The bundle

```php
// config/bundles.php
return [
    // ...
    Base\BaseBundle::class => ['all' => true],
    Base\Mailbox\MailboxBundle::class => ['all' => true],
];
```

`MailboxBundle` aliases `Base\Mailbox\Entity\*` to `App\Entity\Mailbox\*` and the repositories to
`App\Repository\Mailbox\*`; `templates/` is `@Mailbox`, `public/` is `bundles/mailbox/`.

## The routes

```yaml
# config/routes.yaml
mailbox_controller:
    resource: "@MailboxBundle/src/Controller/Client"
    type: attribute
    prefix: /
```

| Route | Path | Methods |
|---|---|---|
| `mailbox_index` | `/messagerie/{box}` - `inbox` (default), `archive`, `sent`, `desk` | any |
| `mailbox_compose` | `/messagerie/nouveau/{to?}` (`?subject=`) | any |
| `mailbox_show` | `/messagerie/{id}` | any |
| `mailbox_state` | `/messagerie/{id}/etat` | GET |
| `mailbox_reply` | `/messagerie/{id}/repondre` | POST |
| `mailbox_action` | `/messagerie/{id}/{archive,unarchive,star,unstar,delete}` | POST |
| `mailbox_batch` | `/messagerie/lot/{archive,delete}` | POST |

Everything is behind `ROLE_USER`, and `mailbox.required_role` on top in each action.

## Configuration

```yaml
# config/packages/mailbox.yaml (every key optional)
mailbox:
    per_page: 20
    inbox_limit: 100          # 0: unlimited
    flood_interval: 120       # seconds between two messages
    subject_max_length: 55
    content_max_length: 10000
    max_recipients: 5
    required_role: ROLE_USER
```

## The schema and the public files

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
bin/console assets:install
```

Tables `mailbox_conversation`, `mailbox_message`, `mailbox_participant` - and
`mailbox_desk_conversation`, `mailbox_attachment` only when `desks` or `attachments` is
configured. The stylesheet `bundles/mailbox/css/mailbox.css`.

## What the layout provides

The templates extend `layout1.html.twig`; `templates/bundles/MailboxBundle/client/_banner.html.twig`
and `_avatar.html.twig` are the override points for the header and a member's picture. The
package's `example/app/` is a minimal host: its `bundles.php`, `routes.yaml`, `doctrine.yaml`,
`security.yaml`, `mailbox.yaml` and `layout1.html.twig`.
