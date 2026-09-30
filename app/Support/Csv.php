<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class Csv
{
    /**
     * Neutralise spreadsheet formula injection: a cell that starts with
     * = + - @ TAB or CR is prefixed with an apostrophe so Excel/Sheets show
     * it as text instead of executing it (OWASP recommendation).
     */
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'".$value : $value;
    }

    /**
     * Stream rows as a UTF-8 CSV with BOM (so Excel opens Arabic correctly).
     *
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(self::cell(...), $headers), escape: '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
