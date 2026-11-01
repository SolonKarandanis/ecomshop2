<?php

namespace App\Models\Concerns;

/**
 * For Translatable models (#81): a translated attribute that's empty in the
 * shown language, like a Turkish name without a Turkish description, comes
 * from the next language that has it, in config('translatable.locales') order.
 * The package's own property fallback only tries a fixed `fallback_locale`,
 * which is null here so that whole translations fall back through every
 * language.
 */
trait FallsBackPerAttribute
{
    protected function getAttributeOrFallback(?string $locale, string $attribute)
    {
        $value = $this->getTranslation($locale)?->$attribute;

        if (! $this->usePropertyFallback() || ! $this->isEmptyTranslatableAttribute($attribute, $value)) {
            return $value;
        }

        foreach ($this->getLocalesHelper()->all() as $fallback) {
            $candidate = $this->getTranslation($fallback, false)?->$attribute;
            if (! $this->isEmptyTranslatableAttribute($attribute, $candidate)) {
                return $candidate;
            }
        }

        return $value;
    }
}
