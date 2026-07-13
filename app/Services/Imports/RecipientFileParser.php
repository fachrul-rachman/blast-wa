<?php

namespace App\Services\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RecipientFileParser
{
    private const MAX_ROWS = 10000;

    public function parse(UploadedFile $file): ParsedImport
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        $rawRows = match ($extension) {
            'csv' => $this->parseCsv($file),
            'xlsx' => $this->parseXlsx($file),
            default => throw new ImportException('Only CSV and XLSX files are supported.'),
        };

        return $this->normalizeRows($rawRows);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new ImportException('Unable to read the uploaded file.');
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;

            if (count($rows) > self::MAX_ROWS + 1) {
                fclose($handle);
                throw new ImportException('Recipient file exceeds the 10,000 row limit.');
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function parseXlsx(UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $worksheetInfo = $reader->listWorksheetInfo($file->getRealPath());

        if (count($worksheetInfo) !== 1) {
            throw new ImportException('XLSX files must contain exactly one sheet.');
        }

        $totalRows = (int) ($worksheetInfo[0]['totalRows'] ?? 0);

        if ($totalRows > self::MAX_ROWS + 1) {
            throw new ImportException('Recipient file exceeds the 10,000 row limit.');
        }

        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rawRows
     */
    private function normalizeRows(array $rawRows): ParsedImport
    {
        $rawRows = $this->dropTrailingEmptyRows($rawRows);

        if ($rawRows === []) {
            throw new ImportException('Recipient file is empty.');
        }

        $headers = $this->headers(array_shift($rawRows));

        if ($headers === []) {
            throw new ImportException('Recipient file must contain usable headers.');
        }

        if (count($rawRows) > self::MAX_ROWS) {
            throw new ImportException('Recipient file exceeds the 10,000 row limit.');
        }

        $rows = [];

        foreach ($rawRows as $index => $rawRow) {
            if ($this->isEmptyRow($rawRow)) {
                continue;
            }

            $data = [];

            foreach ($headers as $position => $header) {
                $data[$header['key']] = $this->cellValue($rawRow[$position] ?? null);
            }

            $rows[] = [
                'source_row_number' => $index + 2,
                'data' => $data,
            ];
        }

        return new ParsedImport($headers, $rows);
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, array{key: string, label: string}>
     */
    private function headers(array $row): array
    {
        $headers = [];
        $used = [];

        foreach ($row as $cell) {
            $label = trim((string) $cell);

            if ($label === '') {
                continue;
            }

            $baseKey = Str::slug($label, '_');
            $baseKey = $baseKey === '' ? 'column' : $baseKey;
            $key = $baseKey;
            $suffix = 2;

            while (in_array($key, $used, true)) {
                $key = "{$baseKey}_{$suffix}";
                $suffix++;
            }

            $used[] = $key;
            $headers[] = ['key' => $key, 'label' => $label];
        }

        return $headers;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    private function dropTrailingEmptyRows(array $rows): array
    {
        while ($rows !== [] && $this->isEmptyRow($rows[array_key_last($rows)])) {
            array_pop($rows);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($this->cellValue($cell) !== null) {
                return false;
            }
        }

        return true;
    }

    private function cellValue(mixed $cell): ?string
    {
        if ($cell === null) {
            return null;
        }

        $value = trim((string) $cell);

        return $value === '' ? null : $value;
    }
}
