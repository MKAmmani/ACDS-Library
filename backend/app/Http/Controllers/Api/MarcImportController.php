<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\MarcParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarcImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        if (!in_array($ext, ['mrc', 'marc'])) {
            return response()->json([
                'message' => 'File must be a binary MARC file (.mrc or .marc).',
            ], 422);
        }

        $content = file_get_contents($request->file('file')->getRealPath());
        if (!$content) {
            return response()->json(['message' => 'Could not read uploaded file.'], 422);
        }

        $parser  = new MarcParser();
        $records = $parser->parseFile($content);

        if (empty($records)) {
            return response()->json([
                'message' => 'No valid MARC records found. Ensure the file is in binary MARC (ISO 2709) format.',
            ], 422);
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::beginTransaction();
        try {
            foreach ($records as $index => $fields) {
                $data = $parser->toBook($fields);

                if (empty($data['title'])) {
                    $errors[] = 'Record ' . ($index + 1) . ': no title found — skipped.';
                    $skipped++;
                    continue;
                }

                // Skip duplicates by ISBN
                if (!empty($data['isbn']) && Book::where('isbn', $data['isbn'])->exists()) {
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
            'total'    => count($records),
            'errors'   => $errors,
        ]);
    }
}
