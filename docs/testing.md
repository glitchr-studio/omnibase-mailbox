---
title: Testing
order: 60
---

# Testing

```bash
make tests                               # phpunit, standalone or from inside a host app
docker compose run --rm test             # the same, in a clean php:8.4 container
```

In omnisense/omnibase's harness, this checkout mounted over the package:

```bash
docker run --rm --network none -v "$PWD":/srv/demo/vendor/omnibase/mailbox:ro omnisense/omnibase-harness test mailbox
```

```
................                                                  16 / 16 (100%)

OK (16 tests, 138 assertions)
```

| File | What |
|---|---|
| `Crypto/MessageCipherTest` | off, the text is stored as it always was; on, it is sealed and comes back; what was written before stays readable; without a usable key nothing is sent; another key or an altered text is not read |
| `Service/ComposeAndTextTest` | recipients split on commas, semicolons and spaces; none is an empty list; a message is escaped and keeps its paragraphs; links become safe anchors; a refusal carries its key and parameters |
| `DependencyInjection/BackwardCompatibilityTest` | every new option is off by default; no table is added unless desks or attachments are configured; a desk is a role, or a role and a label; the service's new arguments are last and optional |
| `Translation/PolitenessCataloguesTest` | a polite variant stands beside its text and takes its parameters; in French no polite text says "tu" |

`MessageCipher` needs no container, run in the harness image:

```php
use Base\Mailbox\Crypto\MessageCipher;
use Base\Mailbox\Service\MailboxException;

$key = base64_encode(str_repeat("\x07", 32));
$cipher = new MessageCipher(true, $key);
$sealed = $cipher->encrypt('Rendez-vous jeudi ?');
echo substr($sealed, 0, 5), ' ', var_export(MessageCipher::isEncrypted($sealed), true), ' ', $cipher->decrypt($sealed), "\n";
var_dump((new MessageCipher(true, base64_encode(str_repeat("\x08", 32))))->decrypt($sealed));
echo (new MessageCipher(false))->encrypt('as it is'), ' | ', $cipher->decrypt('written before'), "\n";
try {
    (new MessageCipher(true, 'not a key'))->encrypt('x');
} catch (MailboxException $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}
```

```
mbx1: true Rendez-vous jeudi ?
NULL
as it is | written before
Base\Mailbox\Service\MailboxException: error.no_key
```

The package also ships a self-contained demo (`Dockerfile`, a bare Symfony skeleton with this
checkout and SQLite): `docker build -t mailbox-demo . && docker run --rm -p 8000:8000 mailbox-demo`.
