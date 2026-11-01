<?php

namespace App\Filament\Support;

use Astrotomic\Translatable\Contracts\Translatable;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament form support for translated catalogue fields (#81): a tab per
 * language, with each field stored as `{locale}.{field}`, which the
 * Translatable models save as that language's translation.
 */
class TranslatableFields
{
    // Each language named in itself, as in the storefront's switcher.
    private const LABELS = ['en' => 'English', 'el' => 'Ελληνικά', 'tr' => 'Türkçe'];

    /** @return list<string> */
    public static function locales(): array
    {
        return config('translatable.locales');
    }

    /**
     * @param  Closure(string $locale): array<int, mixed>  $fields  that language's inputs
     */
    public static function tabs(Closure $fields): Tabs
    {
        return Tabs::make('translations')
            ->tabs(array_map(
                fn (string $locale) => Tab::make(self::LABELS[$locale] ?? $locale)->schema($fields($locale)),
                self::locales(),
            ))
            ->columnSpanFull();
    }

    /** A language's name, required only while no other language has one. */
    public static function name(string $locale): TextInput
    {
        $others = array_values(array_diff(self::locales(), [$locale]));

        return TextInput::make("{$locale}.name")
            ->label('Name')
            ->maxLength(255)
            ->live(onBlur: true)
            ->required(fn (Get $get) => collect($others)->every(fn (string $other) => blank($get("{$other}.name"))));
    }

    /** The first name filled in, in fallback order, e.g. to build a slug from. */
    public static function firstName(Get $get): string
    {
        foreach (self::locales() as $locale) {
            if (filled($get("{$locale}.name"))) {
                return (string) $get("{$locale}.name");
            }
        }

        return '';
    }

    /**
     * The record's translations as form state, for EditRecord::mutateFormDataBeforeFill().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(Model $record, array $data): array
    {
        if (! $record instanceof Translatable) {
            return $data;
        }

        return [...$data, ...$record->getTranslationsArray()];
    }

    /**
     * Drops languages left without a name, which have no translation to save.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<string>} the data, and the languages dropped
     */
    public static function withoutBlankLocales(array $data): array
    {
        $blank = [];
        foreach (self::locales() as $locale) {
            if (blank($data[$locale]['name'] ?? null)) {
                unset($data[$locale]);
                $blank[] = $locale;
            }
        }

        return [$data, $blank];
    }

    /**
     * Saves the form's translations: languages left blank aren't created, and
     * ones the admin cleared are deleted.
     *
     * @param  array<string, mixed>  $data
     */
    public static function update(Model $record, array $data): Model
    {
        [$data, $blank] = self::withoutBlankLocales($data);
        $record->update($data);

        if ($record instanceof Translatable) {
            $record->deleteTranslations($blank);
        }

        return $record;
    }
}
