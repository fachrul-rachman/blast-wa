<?php

namespace App\Services\Imports;

class ColumnDetector
{
    /**
     * @param  array<int, array{key: string, label: string}>  $headers
     * @param  array<int, array{source_row_number: int, data: array<string, string|null>}>  $rows
     */
    public function detectPhoneColumn(array $headers, array $rows): ?string
    {
        return $this->bestColumn($headers, $rows, function (array $header, array $values): int {
            $score = 0;
            $label = strtolower($header['label']);

            if (str_contains($label, 'phone') || str_contains($label, 'telepon') || str_contains($label, 'whatsapp') || str_contains($label, 'nomor') || str_contains($label, 'no hp')) {
                $score += 50;
            }

            $valid = 0;
            $nonEmpty = 0;

            foreach ($values as $value) {
                if ($value === null) {
                    continue;
                }

                $nonEmpty++;

                if (preg_match('/^62\d{8,15}$/', $value) === 1) {
                    $valid++;
                }
            }

            if ($nonEmpty > 0) {
                $score += (int) round(($valid / $nonEmpty) * 50);
            }

            return $score;
        });
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $headers
     * @param  array<int, array{source_row_number: int, data: array<string, string|null>}>  $rows
     */
    public function detectNameColumn(array $headers, array $rows, ?string $phoneColumn): ?string
    {
        return $this->bestColumn($headers, $rows, function (array $header, array $values) use ($phoneColumn): int {
            if ($header['key'] === $phoneColumn) {
                return 0;
            }

            $score = 0;
            $label = strtolower($header['label']);

            if (str_contains($label, 'name') || str_contains($label, 'nama') || str_contains($label, 'customer')) {
                $score += 50;
            }

            $textual = 0;
            $nonEmpty = 0;

            foreach ($values as $value) {
                if ($value === null) {
                    continue;
                }

                $nonEmpty++;

                if (preg_match('/[A-Za-z]/', $value) === 1 && preg_match('/^\d+$/', $value) !== 1) {
                    $textual++;
                }
            }

            if ($nonEmpty > 0) {
                $score += (int) round(($textual / $nonEmpty) * 40);
            }

            return $score;
        }, 40);
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $headers
     * @param  array<int, array{source_row_number: int, data: array<string, string|null>}>  $rows
     */
    private function bestColumn(array $headers, array $rows, callable $scorer, int $minimumScore = 30): ?string
    {
        $bestKey = null;
        $bestScore = 0;
        $tied = false;

        foreach ($headers as $header) {
            $values = array_map(fn (array $row): ?string => $row['data'][$header['key']] ?? null, $rows);
            $score = $scorer($header, $values);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $header['key'];
                $tied = false;
            } elseif ($score === $bestScore && $score > 0) {
                $tied = true;
            }
        }

        if ($bestScore < $minimumScore || $tied) {
            return null;
        }

        return $bestKey;
    }
}
