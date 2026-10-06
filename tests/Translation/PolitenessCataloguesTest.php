<?php

namespace Tests\Base\Mailbox\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The mailbox speaks as a forum does: its French says "tu". A site that says
 * "vous" (glitchr/omnibase's base.translator.politeness: polite) reads each
 * text in its "._polite" variant when there is one - so every French text
 * that says tu has one here, beside it, with the same parameters.
 *
 * The check omnibase runs on its own catalogues
 * (tests/Translation/PolitenessCataloguesTest), on this bundle's.
 */
class PolitenessCataloguesTest extends TestCase
{
    private const LEVELS = ['_plain', '_polite', '_formal'];

    /** French words that address somebody as "tu" (the apostrophes straightened before they are looked for). */
    private const TU = '/(?<![\p{L}\p{N}_-])(tu|te|t\'|ton|ta|tes|toi|tien|tienne)(?![\p{L}\p{N}_-])/iu';

    /** Orders in the singular, which no pronoun gives away: at the start of a sentence. */
    private const ORDER = '/(?:^|[.!?:(]\s*)(Choisis|Écris|Sépare|Réponds|Archive|Indique|Prends|Clique|Saisis|Vérifie|Ajoute|Pense|Utilise|Confirme|Supprime|Envoie)(?=[\s,-])/u';

    /** @return array<string, array<string, string>> file name => flattened key => text */
    private function catalogues(string $language): array
    {
        $catalogues = [];
        foreach (glob(\dirname(__DIR__, 2).'/translations/*.'.$language.'.yaml') as $file) {
            $flat = [];
            $walk = function (array $node, string $prefix) use (&$walk, &$flat): void {
                foreach ($node as $key => $value) {
                    \is_array($value) ? $walk($value, $prefix.$key.'.') : $flat[$prefix.$key] = (string) $value;
                }
            };
            $walk(Yaml::parseFile($file) ?? [], '');
            $catalogues[basename($file)] = $flat;
        }

        return $catalogues;
    }

    /** @return list<string> the parameters of an ICU text: {max}, {count, plural, ...} */
    private function parameters(string $text): array
    {
        preg_match_all('/\{\s*([A-Za-z0-9_]+)\s*[,}]/', $text, $found);
        $names = array_unique($found[1]);
        sort($names);

        return $names;
    }

    private function level(string $key): ?string
    {
        $last = substr((string) strrchr('.'.$key, '.'), 1);

        return \in_array($last, self::LEVELS, true) ? $last : null;
    }

    private function saysTu(string $text): bool
    {
        $text = str_replace('’', '\'', strip_tags($text));

        return 1 === preg_match(self::TU, $text) || 1 === preg_match(self::ORDER, $text);
    }

    public function testAVariantStandsBesideItsTextAndTakesItsParameters(): void
    {
        $variants = 0;
        foreach (['fr', 'en'] as $language) {
            foreach ($this->catalogues($language) as $file => $texts) {
                foreach ($texts as $key => $text) {
                    if (null === $level = $this->level($key)) {
                        continue;
                    }
                    ++$variants;
                    $base = substr($key, 0, -\strlen('.'.$level));
                    $this->assertArrayHasKey($base, $texts, "$file: \"$key\" is a variant of a text that is not there");
                    $this->assertSame($this->parameters($texts[$base]), $this->parameters($text), "$file: \"$key\" does not take the parameters of its text");
                    $this->assertNotSame($texts[$base], $text, "$file: \"$key\" says what its text already says");
                }
            }
        }

        $this->assertGreaterThan(0, $variants, 'the mailbox writes its polite French');
    }

    public function testInFrenchAPoliteVariantNoLongerSaysTuAndNoTextSaysItWithoutOne(): void
    {
        $left = [];
        foreach ($this->catalogues('fr') as $file => $texts) {
            foreach ($texts as $key => $text) {
                if ('_polite' === $this->level($key)) {
                    $this->assertFalse($this->saysTu($text), "$file: \"$key\" still says tu: $text");
                } elseif (null === $this->level($key) && $this->saysTu($text) && !isset($texts[$key.'._polite'])) {
                    $left[] = "$file: $key";
                }
            }
        }

        $this->assertSame([], $left, 'French texts that say "tu" and have no "._polite" variant');
    }
}
