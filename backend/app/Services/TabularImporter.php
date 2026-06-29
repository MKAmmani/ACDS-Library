<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Imports catalog records from spreadsheets (Excel/CSV) and Microsoft Access
 * databases. Each row is mapped to a Book by matching column headers against a
 * set of aliases, returning the same array shape as MarcParser::toBook().
 */
class TabularImporter
{
    /** Normalised header alias → canonical Book field. */
    private array $aliases = [
        // title
        'title' => 'title', 'booktitle' => 'title', 'name' => 'title', 'bookname' => 'title',
        // authors (incl. common "AUTHOURS" misspelling)
        'author' => 'authors', 'authors' => 'authors', 'authour' => 'authors',
        'authours' => 'authors', 'by' => 'authors', 'creator' => 'authors', 'writtenby' => 'authors',
        // isbn
        'isbn' => 'isbn', 'isbn13' => 'isbn', 'isbn10' => 'isbn', 'isbnno' => 'isbn',
        // publisher
        'publisher' => 'publisher', 'publishers' => 'publisher', 'pub' => 'publisher', 'publishedby' => 'publisher',
        // year (incl. combined "PLACE_AND_YEAR" — the 4-digit year is extracted)
        'year' => 'year', 'published' => 'year', 'publicationyear' => 'year',
        'pubyear' => 'year', 'yearpublished' => 'year', 'datepublished' => 'year',
        'placeandyear' => 'year', 'placeyear' => 'year',
        // edition
        'edition' => 'edition', 'ed' => 'edition',
        // description
        'description' => 'description', 'abstract' => 'description',
        'summary' => 'description', 'notes' => 'description', 'note' => 'description',
        'remarks' => 'description', 'remark' => 'description',
        // subject
        'subject' => 'subject_area', 'subjectarea' => 'subject_area',
        'category' => 'subject_area', 'topic' => 'subject_area', 'genre' => 'subject_area',
        // call number
        'callnumber' => 'call_number', 'callno' => 'call_number',
        'classmark' => 'call_number', 'call' => 'call_number', 'classification' => 'call_number',
        // shelf
        'shelf' => 'shelf_location', 'shelflocation' => 'shelf_location', 'location' => 'shelf_location',
        // language
        'language' => 'language', 'lang' => 'language',
        // copies
        'copies' => 'number_of_copies', 'numberofcopies' => 'number_of_copies',
        'quantity' => 'number_of_copies', 'qty' => 'number_of_copies',
        'noofcopies' => 'number_of_copies', 'totalcopies' => 'number_of_copies',
        // format
        'format' => 'format', 'materialtype' => 'format', 'type' => 'format',
    ];

    // ── Spreadsheet (xlsx / xls / xlsm / ods / csv) ───────────────────────────

    public function fromSpreadsheet(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not read the spreadsheet: ' . $e->getMessage());
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        // Drop fully-empty leading rows, then treat the first remaining row as headers.
        $rows = array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($c) => trim((string) $c) !== '')) > 0));

        if (count($rows) < 2) {
            throw new RuntimeException('The spreadsheet has no data rows. Expected a header row plus at least one record.');
        }

        $headers = array_shift($rows);
        $map     = $this->buildColumnMap($headers);

        if (! in_array('title', $map, true)) {
            throw new RuntimeException('No "Title" column found. Add a header row with at least a Title column.');
        }

        $books = [];
        foreach ($rows as $row) {
            $assoc = [];
            foreach ($map as $index => $field) {
                $assoc[$field] = $row[$index] ?? null;
            }
            $book = $this->rowToBook($assoc);
            if (! empty($book['title'])) {
                $books[] = $book;
            }
        }

        return $books;
    }

    // ── Microsoft Access (mdb / accdb) ────────────────────────────────────────

    public function fromAccess(string $path): array
    {
        if (! function_exists('odbc_connect')) {
            throw new RuntimeException('Access import requires the PHP ODBC extension, which is not enabled on the server.');
        }

        $dsn  = "Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq={$path};";
        $conn = @odbc_connect($dsn, '', '');

        if (! $conn) {
            throw new RuntimeException('Could not open the Access database. The Microsoft Access ODBC driver may be missing. ' . odbc_errormsg());
        }

        try {
            $table = $this->pickAccessTable($conn);
            if (! $table) {
                throw new RuntimeException('No suitable table with a Title column was found in the Access database.');
            }

            $result = odbc_exec($conn, 'SELECT * FROM [' . $table . ']');
            if (! $result) {
                throw new RuntimeException('Could not read table "' . $table . '": ' . odbc_errormsg($conn));
            }

            // Column-name → canonical map for this table.
            $colCount = odbc_num_fields($result);
            $map = [];
            for ($i = 1; $i <= $colCount; $i++) {
                $name  = odbc_field_name($result, $i);
                $canon = $this->aliases[$this->normalize($name)] ?? null;
                if ($canon) {
                    $map[$name] = $canon;
                }
            }

            $books = [];
            while ($row = odbc_fetch_array($result)) {
                $assoc = [];
                foreach ($map as $col => $field) {
                    $assoc[$field] = $row[$col] ?? null;
                }
                $book = $this->rowToBook($assoc);
                if (! empty($book['title'])) {
                    $books[] = $book;
                }
            }

            return $books;
        } finally {
            odbc_close($conn);
        }
    }

    /** Choose the user table whose columns best match Book fields (must have a title). */
    private function pickAccessTable($conn): ?string
    {
        $tablesRes = odbc_tables($conn);
        $candidates = [];

        while ($t = odbc_fetch_array($tablesRes)) {
            $type = $t['TABLE_TYPE'] ?? '';
            $name = $t['TABLE_NAME'] ?? '';
            if ($type !== 'TABLE' || $name === '' || str_starts_with($name, 'MSys') || str_starts_with($name, '~')) {
                continue;
            }
            $candidates[] = $name;
        }

        $best      = null;
        $bestScore = 0;

        foreach ($candidates as $name) {
            $res = @odbc_exec($conn, 'SELECT * FROM [' . $name . ']');
            if (! $res) {
                continue;
            }
            $hasTitle = false;
            $score    = 0;
            $count    = odbc_num_fields($res);
            for ($i = 1; $i <= $count; $i++) {
                $canon = $this->aliases[$this->normalize(odbc_field_name($res, $i))] ?? null;
                if ($canon) {
                    $score++;
                    if ($canon === 'title') {
                        $hasTitle = true;
                    }
                }
            }
            if ($hasTitle && $score > $bestScore) {
                $bestScore = $score;
                $best      = $name;
            }
        }

        return $best;
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    /** Map column index → canonical Book field from a header row. */
    private function buildColumnMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $canon = $this->aliases[$this->normalize((string) $header)] ?? null;
            if ($canon) {
                $map[$index] = $canon;
            }
        }
        return $map;
    }

    private function normalize(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($header)));
    }

    /** Coerce any byte string into valid UTF-8 (treats invalid input as Windows-1252). */
    private function toUtf8(string $v): string
    {
        if ($v === '' || mb_check_encoding($v, 'UTF-8')) {
            return $v;
        }
        return mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
    }

    /** Convert a normalised assoc row → complete Book attribute array. */
    private function rowToBook(array $row): array
    {
        // Legacy sources (Access ANSI, old Excel) often emit Windows-1252 bytes,
        // which break json_encode later. Normalise every string to valid UTF-8.
        $row = array_map(fn ($v) => is_string($v) ? $this->toUtf8($v) : $v, $row);

        $title = trim((string) ($row['title'] ?? ''));

        $copies = (int) ($row['number_of_copies'] ?? 1);
        if ($copies < 1) {
            $copies = 1;
        }

        $year = null;
        if (! empty($row['year']) && preg_match('/(\d{4})/', (string) $row['year'], $m)) {
            $y = (int) $m[1];
            if ($y >= 1000 && $y <= (int) date('Y')) {
                $year = $y;
            }
        }

        $isbn = null;
        if (! empty($row['isbn'])) {
            $isbn = preg_replace('/[^0-9X\-]/', '', strtoupper((string) $row['isbn']));
            $isbn = substr(trim($isbn, '-'), 0, 20) ?: null;
        }

        return array_filter([
            'title'            => $title ?: null,
            'authors'          => trim((string) ($row['authors'] ?? '')) ?: null,
            'isbn'             => $isbn,
            'publisher'        => trim((string) ($row['publisher'] ?? '')) ?: null,
            'year'             => $year,
            'edition'          => trim((string) ($row['edition'] ?? '')) ?: null,
            'description'      => trim((string) ($row['description'] ?? '')) ?: null,
            'call_number'      => trim((string) ($row['call_number'] ?? '')) ?: null,
            'shelf_location'   => trim((string) ($row['shelf_location'] ?? '')) ?: null,
            'subject_area'     => trim((string) ($row['subject_area'] ?? '')) ?: 'General',
            'language'         => trim((string) ($row['language'] ?? '')) ?: 'English',
            'format'           => trim((string) ($row['format'] ?? '')) ?: 'Book (Physical)',
            'number_of_copies' => $copies,
            'available_copies' => $copies,
            'cover_treatment'  => 'cv-blue',
        ], fn ($v) => $v !== null && $v !== '');
    }
}
