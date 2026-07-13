<?php

namespace App\Services\Imports;

class PhoneNumberValidator
{
    /**
     * @return array{normalized: string|null, errors: array<int, string>}
     */
    public function validate(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return ['normalized' => null, 'errors' => ['Phone number is empty.']];
        }

        $normalized = preg_replace('/[\s().-]/', '', trim($value));

        if (! is_string($normalized) || $normalized === '') {
            return ['normalized' => null, 'errors' => ['Phone number is empty.']];
        }

        if (str_starts_with($normalized, '+62')) {
            return ['normalized' => $normalized, 'errors' => ['Phone number must start with 62, not +62.']];
        }

        if (str_starts_with($normalized, '0')) {
            return ['normalized' => $normalized, 'errors' => ['Phone number must start with 62, not 0.']];
        }

        if (preg_match('/^\d+$/', $normalized) !== 1) {
            return ['normalized' => $normalized, 'errors' => ['Phone number must contain digits only.']];
        }

        if (! str_starts_with($normalized, '62')) {
            return ['normalized' => $normalized, 'errors' => ['Phone number must start with 62.']];
        }

        if (strlen($normalized) < 10 || strlen($normalized) > 17) {
            return ['normalized' => $normalized, 'errors' => ['Phone number length is not valid.']];
        }

        return ['normalized' => $normalized, 'errors' => []];
    }
}
