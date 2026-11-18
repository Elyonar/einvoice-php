<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SPELLING (ruling R230, 18 Nov 2026): "organization" is written with a z in this repository, in
 * words people read, identifiers, comments, tests, file names, example names and guide ids. The rule
 * covers that one word family and every cased or joined form of it (sub-organization,
 * SubOrganizations, ORGANIZATION); every other British spelling (authorise, colour, licence) stays.
 *
 * The s spelling may appear only where ALLOWED says, each entry with its reason. An entry that no
 * longer matches anything fails as well, so the list can only shrink: the generated files leave it
 * when `make sync` regenerates them from an API document that serves the z spelling.
 */
final class SpellingTest extends TestCase
{
    /** The s spelling of the word family, in any case and inside any identifier. */
    private const S_SPELLING = '/organi[s](?:e|ing|ation)/i';

    private const ALLOWED = [
        'scripts/openapi-api-key-ops.json' => 'copied from einvoice-js by `make sync`, which takes it from the services\' API document',
        'src/Generated/Types.php' => 'generated from that snapshot by `make sync`',
    ];

    /**
     * CHANGELOG.md is history: every released entry stays as it was written. Above the first released
     * version, the s spelling may appear only inside a code span, where an entry quotes an old value
     * or name in backticks; never in its prose.
     */
    private const RELEASED = '/^#{1,6} \[?\d+\.\d+\.\d+/';

    private const CODE_SPAN = '/`[^`\n]*`/';

    /** @return list<string> */
    private static function files(): array
    {
        $root = dirname(__DIR__);
        $command = 'git -C ' . escapeshellarg($root) . ' ls-files -z --cached --others --exclude-standard';
        $out = shell_exec($command);
        self::assertIsString($out, 'git ls-files failed');
        $files = array_values(array_unique(array_filter(
            explode("\0", $out),
            fn (string $f): bool => $f !== '' && file_exists($root . '/' . $f),
        )));
        sort($files);

        return $files;
    }

    /** @return list<string> the `file:line` of every s spelling the rule does not allow in this file's text */
    private static function offences(string $file, string $text): array
    {
        if (array_key_exists($file, self::ALLOWED)) {
            return [];
        }
        $lines = explode("\n", $text);
        $released = -1;
        if ($file === 'CHANGELOG.md') {
            foreach ($lines as $i => $line) {
                if (preg_match(self::RELEASED, $line) === 1) {
                    $released = $i;
                    break;
                }
            }
        }
        $found = [];
        foreach ($lines as $i => $line) {
            if ($released !== -1 && $i >= $released) {
                break;
            }
            $checked = $file === 'CHANGELOG.md' ? (string) preg_replace(self::CODE_SPAN, '', $line) : $line;
            if (preg_match(self::S_SPELLING, $checked) === 1) {
                $found[] = sprintf('%s:%d: %s', $file, $i + 1, substr(trim($line), 0, 120));
            }
        }

        return $found;
    }

    /** The words are assembled so that this file carries no s spelling itself. */
    private static function s(string $head, string $tail): string
    {
        return $head . $tail;
    }

    public function testReadsTheRepository(): void
    {
        $files = self::files();
        self::assertContains('composer.json', $files);
        self::assertContains('tests/SpellingTest.php', $files);
    }

    public function testNoFileNameOrPathCarriesTheSSpelling(): void
    {
        self::assertSame([], array_values(array_filter(
            self::files(),
            fn (string $f): bool => preg_match(self::S_SPELLING, $f) === 1,
        )));
    }

    public function testNoFileCarriesItOutsideTheAllowedPlaces(): void
    {
        $root = dirname(__DIR__);
        $found = [];
        foreach (self::files() as $f) {
            $text = file_get_contents($root . '/' . $f);
            self::assertIsString($text);
            if (str_contains($text, "\0")) {
                continue;
            }
            array_push($found, ...self::offences($f, $text));
        }
        self::assertSame([], $found);
    }

    /** @return iterable<string, array{string}> */
    public static function allowed(): iterable
    {
        foreach (array_keys(self::ALLOWED) as $file) {
            yield $file => [$file];
        }
    }

    #[DataProvider('allowed')]
    public function testAnAllowedFileStillCarriesTheSSpelling(string $file): void
    {
        self::assertContains($file, self::files());
        $text = file_get_contents(dirname(__DIR__) . '/' . $file);
        self::assertIsString($text);
        self::assertSame(1, preg_match(self::S_SPELLING, $text));
    }

    /** @return iterable<string, array{string}> */
    public static function sSpellings(): iterable
    {
        $words = [
            self::s('organi', 'sation'), self::s('Organi', 'sations'), self::s('ORGANI', 'SATION'),
            self::s('organi', 'sational'), self::s('organi', 'se'), self::s('organi', 'sed'), self::s('organi', 'ses'),
            self::s('organi', 'sing'), self::s('organi', 'ser'), self::s('sub-organi', 'sation'),
            self::s('SubOrgani', 'sations'), self::s('sub_organi', 'sations'), self::s('organi', 'sationId'),
            self::s('IssuesAsOrgani', 'sation'),
        ];
        foreach ($words as $word) {
            yield $word => [$word];
        }
    }

    #[DataProvider('sSpellings')]
    public function testTheCheckCatches(string $word): void
    {
        self::assertSame(1, preg_match(self::S_SPELLING, $word));
        self::assertCount(1, self::offences('README.md', "a line with {$word} in it"));
    }

    /** @return iterable<string, array{string}> */
    public static function otherWords(): iterable
    {
        foreach (['organization', 'Organizations', 'ORGANIZATION', 'organizational', 'organize', 'sub-organization', 'organism', 'authorise', 'colour', 'licence', 'centre'] as $word) {
            yield $word => [$word];
        }
    }

    #[DataProvider('otherWords')]
    public function testTheCheckLeavesAlone(string $word): void
    {
        self::assertSame(0, preg_match(self::S_SPELLING, $word));
    }

    public function testInTheChangelogHistoryAndQuotedOldValuesAreAllowedButProseAboveTheReleasesIsNot(): void
    {
        $old = self::s('organi', 'sation');
        $changelog = implode("\n", [
            '# Changelog',
            '## Unreleased',
            "- `issuedAs.kind` answers `organization` where it answered `{$old}`.",
            "- Every {$old} reads with a z.",
            '## 0.2.2 (2026-11-12)',
            "- The {$old}'s connection.",
        ]);
        self::assertSame(["CHANGELOG.md:4: - Every {$old} reads with a z."], self::offences('CHANGELOG.md', $changelog));
        self::assertCount(3, self::offences('README.md', $changelog));
    }
}
