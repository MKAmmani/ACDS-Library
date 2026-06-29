<?php

namespace App\Services;

/**
 * Parses binary MARC 21 (ISO 2709) files and maps records to Book fields.
 */
class MarcParser
{
    // ── File-level parsing ────────────────────────────────────────────────────

    public function parseFile(string $content): array
    {
        $records = [];
        $pos     = 0;
        $total   = strlen($content);

        while ($pos < $total) {
            if ($pos + 5 > $total) break;

            $recLen = (int) substr($content, $pos, 5);
            if ($recLen < 24 || ($pos + $recLen) > $total) break;

            $raw = substr($content, $pos, $recLen);
            if ($parsed = $this->parseRecord($raw)) {
                $records[] = $parsed;
            }

            $pos += $recLen;
        }

        return $records;
    }

    // ── Record-level parsing ──────────────────────────────────────────────────

    private function parseRecord(string $raw): ?array
    {
        if (strlen($raw) < 24) return null;

        $baseAddr = (int) substr($raw, 12, 5);
        if ($baseAddr < 25 || $baseAddr > strlen($raw)) return null;

        $fields = [];

        // Directory: bytes 24 → (baseAddr - 2), each entry is exactly 12 bytes
        for ($i = 24; ($i + 12) <= ($baseAddr - 1); $i += 12) {
            $tag      = substr($raw, $i, 3);
            $fieldLen = (int) substr($raw, $i + 3, 4);
            $fieldOff = (int) substr($raw, $i + 7, 5);

            $start = $baseAddr + $fieldOff;
            if ($start < 0 || ($start + $fieldLen) > strlen($raw)) continue;

            $fieldData = rtrim(substr($raw, $start, $fieldLen), "\x1E\x1D");

            if ((int) $tag < 10) {
                // Control field — no indicators, no subfields
                $fields[$tag] = $fieldData;
            } else {
                // Variable field — first 2 bytes are indicators
                if (strlen($fieldData) < 2) continue;

                $subfields = [];
                $parts     = explode("\x1F", substr($fieldData, 2));
                foreach ($parts as $part) {
                    if (strlen($part) < 2) continue;
                    $code  = $part[0];
                    $value = trim(substr($part, 1));
                    if ($value !== '') $subfields[$code][] = $value;
                }

                $fields[$tag][] = [
                    'ind1'      => $fieldData[0],
                    'ind2'      => $fieldData[1],
                    'subfields' => $subfields,
                ];
            }
        }

        return $fields ?: null;
    }

    // ── Subfield helpers ──────────────────────────────────────────────────────

    private function sf(array $fields, string $tag, string $code, ?string $default = null): ?string
    {
        $instances = $fields[$tag] ?? [];
        if (empty($instances)) return $default;
        return $instances[0]['subfields'][$code][0] ?? $default;
    }

    private function allSf(array $fields, string $tag, string $code): array
    {
        $result = [];
        foreach ($fields[$tag] ?? [] as $instance) {
            foreach ($instance['subfields'][$code] ?? [] as $val) {
                $result[] = $val;
            }
        }
        return $result;
    }

    // ── Map MARC fields → Book model array ────────────────────────────────────

    public function toBook(array $fields): array
    {
        // Title: 245 $a + $b (strip trailing punctuation)
        $titleA = $this->sf($fields, '245', 'a', '');
        $titleB = $this->sf($fields, '245', 'b', '');
        $title  = trim(rtrim(trim($titleA . ' ' . $titleB), ' /:.,'));

        // Authors: 100/110/111 $a (main entry), 700 $a (added entries)
        $mainAuthor   = $this->sf($fields, '100', 'a')
                     ?? $this->sf($fields, '110', 'a')
                     ?? $this->sf($fields, '111', 'a')
                     ?? '';
        $addedAuthors = $this->allSf($fields, '700', 'a');
        $allAuthors   = array_filter(array_merge(
            [$mainAuthor ? rtrim($mainAuthor, ' ,') : null],
            array_map(fn ($a) => rtrim($a, ' ,'), $addedAuthors)
        ));
        $authors = implode('; ', $allAuthors);

        // ISBN: 020 $a — strip qualifiers like "(hardcover)", keep digits/hyphens/X
        $isbnRaw = $this->sf($fields, '020', 'a');
        $isbn    = null;
        if ($isbnRaw) {
            $isbn = preg_replace('/[^0-9X\-].*/', '', strtoupper(trim($isbnRaw)));
            $isbn = substr(trim($isbn, '-'), 0, 20) ?: null;
        }

        // Publisher & year: prefer 264 (RDA), fall back to 260 (AACR2)
        $publisher = $this->sf($fields, '264', 'b') ?? $this->sf($fields, '260', 'b');
        if ($publisher) $publisher = trim(rtrim($publisher, ' ,:'));

        $yearRaw = $this->sf($fields, '264', 'c') ?? $this->sf($fields, '260', 'c');
        $year    = null;
        if ($yearRaw && preg_match('/(\d{4})/', $yearRaw, $m)) {
            $y = (int) $m[1];
            if ($y >= 1000 && $y <= (int) date('Y')) $year = $y;
        }

        // Edition: 250 $a
        $edition = $this->sf($fields, '250', 'a');

        // Abstract / description: 520 $a
        $description = $this->sf($fields, '520', 'a');

        // Call number: LC 050 $a+$b → Dewey 082 $a
        $lcA        = $this->sf($fields, '050', 'a', '');
        $lcB        = $this->sf($fields, '050', 'b', '');
        $callNumber = $lcA ? trim($lcA . $lcB) : ($this->sf($fields, '082', 'a') ?? null);

        // Subject area: first 650 $a
        $subjectArea = $this->sf($fields, '650', 'a');
        if ($subjectArea) $subjectArea = rtrim(trim($subjectArea), '.');

        // Language: 041 $a → fallback to MARC 008 fixed field bytes 35–37
        $langCode = $this->sf($fields, '041', 'a');
        if (!$langCode && !empty($fields['008']) && strlen((string) $fields['008']) >= 38) {
            $langCode = substr((string) $fields['008'], 35, 3);
        }
        $language = $this->mapLanguage($langCode);

        return array_filter([
            'title'            => $title ?: null,
            'authors'          => $authors ?: null,
            'isbn'             => $isbn,
            'publisher'        => $publisher,
            'year'             => $year,
            'edition'          => $edition,
            'description'      => $description,
            'call_number'      => $callNumber,
            'subject_area'     => $subjectArea ?: 'General',
            'language'         => $language,
            'format'           => 'Book (Physical)',
            'number_of_copies' => 1,
            'available_copies' => 1,
            'cover_treatment'  => 'cv-blue',
        ], fn ($v) => $v !== null && $v !== '');
    }

    // ── Language code → readable name ─────────────────────────────────────────

    private function mapLanguage(?string $code): string
    {
        if (!$code) return 'English';
        $map = [
            'eng' => 'English',    'ara' => 'Arabic',     'fre' => 'French',
            'ger' => 'German',     'spa' => 'Spanish',    'hau' => 'Hausa',
            'yor' => 'Yoruba',     'ibo' => 'Igbo',       'por' => 'Portuguese',
            'rus' => 'Russian',    'zho' => 'Chinese',    'jpn' => 'Japanese',
            'ita' => 'Italian',    'dut' => 'Dutch',      'swa' => 'Swahili',
        ];
        return $map[strtolower(substr(trim($code), 0, 3))] ?? 'English';
    }
}
