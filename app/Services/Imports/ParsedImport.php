<?php

namespace App\Services\Imports;

/**
 * @param  array<int, array{key: string, label: string}>  $headers
 * @param  array<int, array{source_row_number: int, data: array<string, string|null>}>  $rows
 */
readonly class ParsedImport
{
    /**
     * @param  array<int, array{key: string, label: string}>  $headers
     * @param  array<int, array{source_row_number: int, data: array<string, string|null>}>  $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {}
}
