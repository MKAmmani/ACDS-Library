<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\MarcParser;
use App\Services\TabularImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarcImportController extends Controller
{
    private const MARC        = ['mrc', 'marc'];
    private const SPREADSHEET = ['xlsx', 'xls', 'xlsm', 'ods', 'csv'];
    private const ACCESS      = ['accdb', 'mdb'];

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        // ── Parse into a list of Book attribute arrays based on file type ──────
        try {
            if (in_array($ext, self::MARC, true)) {
                $books = $this->parseMarc($path);
            } elseif (in_array($ext, self::SPREADSHEET, true)) {
                $books = (new TabularImporter())->fromSpreadsheet($path);
            } elseif (in_array($ext, self::ACCESS, true)) {
                $books = (new TabularImporter())->fromAccess($path);
            } else {
                return response()->json([
                    'message' => 'Unsupported file type. Upload a MARC (.mrc, .marc), Excel (.xlsx, .xls, .csv), or Access (.accdb, .mdb) file.',
                ], 422);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (empty($books)) {
            return response()->json([
                'message' => 'No valid records found in the file.',
            ], 422);
        }

        // ── Persist ──────────────────────────────────────────────────────────
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::beginTransaction();
        try {
            foreach ($books as $index => $data) {
                if (empty($data['title'])) {
                    $errors[] = 'Record ' . ($index + 1) . ': no title found — skipped.';
                    $skipped++;
                    continue;
                }

                // Skip duplicates by ISBN
                if (! empty($data['isbn']) && Book::where('isbn', $data['isbn'])->exists()) {
                    $skipped++;
                    continue;
                }

                Book::create($data);
                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'imported' => $imported,
            'skipped'  => $skipped,
            'total'    => count($books),
            'errors'   => $errors,
        ]);
    }

    private function parseMarc(string $path): array
    {
        $content = file_get_contents($path);
        if (! $content) {
            throw new \RuntimeException('Could not read uploaded file.');
        }

        $parser  = new MarcParser();
        $records = $parser->parseFile($content);

        if (empty($records)) {
            throw new \RuntimeException('No valid MARC records found. Ensure the file is in binary MARC (ISO 2709) format.');
        }

        return array_map(fn ($fields) => $parser->toBook($fields), $records);
    }
}
