<?php

namespace App\Console\Commands;

use App\Models\InstitutionalRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('repository:migrate-to-r2')]
#[Description('Copy institutional repository files still present on the local disk to Cloudflare R2 (s3 disk)')]
class MigrateRepositoryFilesToR2 extends Command
{
    public function handle(): void
    {
        $items = InstitutionalRepository::whereNotNull('file_path')->get();

        $copied = 0;
        $missing = 0;
        $alreadyOnR2 = 0;

        foreach ($items as $item) {
            if (Storage::disk('s3')->exists($item->file_path)) {
                $alreadyOnR2++;
                continue;
            }

            if (! Storage::disk('local')->exists($item->file_path)) {
                $this->warn("Missing on local disk, skipping: [{$item->id}] {$item->title} ({$item->file_path})");
                $missing++;
                continue;
            }

            $stream = Storage::disk('local')->readStream($item->file_path);
            Storage::disk('s3')->writeStream($item->file_path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->info("Copied: [{$item->id}] {$item->title}");
            $copied++;
        }

        $this->newLine();
        $this->info("Done. Copied: {$copied}, already on R2: {$alreadyOnR2}, missing on local: {$missing}.");
    }
}
