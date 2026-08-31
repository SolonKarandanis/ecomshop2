<?php

namespace App\Validation\Concerns;

use App\Attributes\Required;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use ReflectionClass;

trait ValidatesAttributes
{
    /**
     * @throws ValidationException
     */
    public function validate(): void
    {
        // Reflect on the class that uses this trait so we can inspect its properties at runtime.
        $reflection = new ReflectionClass($this);
        $rules = [];
        $data  = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();

            // Private/protected properties are not accessible by default via Reflection — this unlocks them.
            $property->setAccessible(true);

            // Read the current value; fall back to null if the property was never initialised
            // (e.g. an optional field that was never set), so the validator still sees the key.
            $data[$name] = $property->isInitialized($this) ? $property->getValue($this) : null;

            // Check whether this property carries a #[Required] attribute.
            // getAttributes() returns only the attributes that match the given class name.
            if (!empty($property->getAttributes(Required::class))) {
                $rules[$name][] = 'required';
            }
        }

        // Nothing to validate — no properties are annotated, skip the Validator call entirely.
        if (empty($rules)) {
            return;
        }

        // Hand off to Laravel's Validator so error messages and behaviour are consistent
        // with the rest of the application (Form Requests, manual validation, etc.).
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
