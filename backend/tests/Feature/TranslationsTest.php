<?php

use Symfony\Component\Finder\Finder;

/**
 * Every literal key passed to __() / trans() in app/. Keys built at runtime
 * (e.g. __("product-attributes.{$name}")) are skipped.
 *
 * @return array<string, array{string, string}>
 */
function literalTranslationKeys(): array
{
    $keys = [];

    // Datasets are built before the app boots, so app_path() isn't available here.
    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/app')->name('*.php') as $file) {
        preg_match_all(
            '/\b(?:__|trans|trans_choice)\(\s*([\'"])([^\'"$]+)\1/',
            $file->getContents(),
            $matches,
        );

        foreach ($matches[2] as $key) {
            $keys[$key] ??= [$key, $file->getRelativePathname()];
        }
    }

    ksort($keys);

    return $keys;
}

it('finds the literal translation keys used in app/', function () {
    expect(literalTranslationKeys())->not->toBeEmpty();
});

it('resolves every literal translation key used in app/', function (string $key, string $file) {
    expect(__($key))->not->toBe($key, "Translation key [{$key}] used in {$file} has no translation.");
})->with(literalTranslationKeys());

/**
 * Every key in lang/en/*.php, flattened with dots, paired with each other language.
 *
 * @return array<string, array{string, string}>
 */
function translationFilesPerLocale(): array
{
    $sets = [];

    foreach (glob(dirname(__DIR__, 2).'/lang/en/*.php') as $path) {
        foreach (['el', 'tr'] as $locale) {
            $sets[$locale.'/'.basename($path)] = [basename($path), $locale];
        }
    }

    return $sets;
}

function flattenTranslations(array $lines, string $prefix = ''): array
{
    $flat = [];

    foreach ($lines as $key => $line) {
        is_array($line)
            ? $flat += flattenTranslations($line, "{$prefix}{$key}.")
            : $flat["{$prefix}{$key}"] = $line;
    }

    return $flat;
}

it('has every English key in the Greek and Turkish files', function (string $file, string $locale) {
    $english = flattenTranslations(require lang_path("en/{$file}"));
    $path = lang_path("{$locale}/{$file}");

    expect($path)->toBeFile();
    expect(array_keys(array_diff_key($english, flattenTranslations(require $path))))->toBe([]);
})->with(translationFilesPerLocale());
