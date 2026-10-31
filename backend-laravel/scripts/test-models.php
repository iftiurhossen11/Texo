<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$doc = App\Models\Document::create([
    'title' => 'Test',
    'original_filename' => 't.txt',
    'file_type' => 'txt',
    'storage_path' => 'documents/t.txt',
    'doc_type' => 'sop',
    'status' => 'uploaded',
]);
$chunk = $doc->chunks()->create([
    'chunk_index' => 0,
    'content' => 'hello',
    'token_count' => 1,
    'vector_id' => 'v1',
]);
echo "chunks={$doc->chunks()->count()} doc_title={$chunk->document->title}\n";
$doc->chunks()->delete();
$doc->delete();
echo "OK\n";
