<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidDocumentMagicBytes implements ValidationRule
{
    private const MIME_TO_TYPE = [
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'text/markdown' => 'md',
        'text/html' => 'html',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    private const EXTENSION_TO_TYPE = [
        'pdf' => 'pdf',
        'txt' => 'txt',
        'csv' => 'csv',
        'md' => 'md',
        'docx' => 'docx',
        'html' => 'html',
        'htm' => 'html',
        'mhtml' => 'html',
    ];

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a valid file.');

            return;
        }

        $mimeType = $value->getMimeType() ?? '';
        $extension = strtolower($value->getClientOriginalExtension());
        $expectedType = self::MIME_TO_TYPE[$mimeType]
            ?? self::EXTENSION_TO_TYPE[$extension]
            ?? null;

        if ($expectedType === null) {
            $fail('The :attribute has an unsupported file type.');

            return;
        }

        $handle = fopen($value->getRealPath(), 'rb');

        if ($handle === false) {
            $fail('The :attribute could not be read.');

            return;
        }

        $header = fread($handle, 8) ?: '';
        fclose($handle);

        if (! $this->matchesMagicBytes($expectedType, $header, $value)) {
            $fail('The :attribute content does not match its declared file type.');
        }
    }

    private function matchesMagicBytes(string $type, string $header, UploadedFile $file): bool
    {
        return match ($type) {
            'pdf' => str_starts_with($header, '%PDF'),
            'docx' => str_starts_with($header, "PK\x03\x04"),
            'txt', 'csv', 'md', 'html' => $this->isPlainTextFile($file),
            default => false,
        };
    }

    private function isPlainTextFile(UploadedFile $file): bool
    {
        $sample = file_get_contents($file->getRealPath(), false, null, 0, 8192);

        if ($sample === false || $sample === '') {
            return false;
        }

        if (str_contains($sample, "\0")) {
            return false;
        }

        if (! mb_check_encoding($sample, 'UTF-8')) {
            return false;
        }

        return true;
    }
}
