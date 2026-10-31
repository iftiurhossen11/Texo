<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $sourceDirectory = dirname(base_path()).DIRECTORY_SEPARATOR.'Project Demo Document';

        if (! is_dir($sourceDirectory)) {
            $this->command?->warn("Demo document directory not found: {$sourceDirectory}");

            return;
        }

        $qaUser = User::query()->where('email', 'qa@texo.com')->first();

        if ($qaUser === null) {
            throw new RuntimeException('The qa@texo.com demo user must be seeded before demo documents.');
        }

        $files = array_values(array_filter(
            scandir($sourceDirectory) ?: [],
            fn (string $filename): bool => $filename !== '.' && $filename !== '..'
                && is_file($sourceDirectory.DIRECTORY_SEPARATOR.$filename)
                && ! Str::endsWith(Str::lower($filename), '-overview.txt'),
        ));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        Document::query()
            ->where('original_filename', 'Demo.txt')
            ->delete();

        foreach ($files as $filename) {
            $sourcePath = $sourceDirectory.DIRECTORY_SEPARATOR.$filename;
            $mapping = $this->mappingFor($filename);
            $databaseFilename = Str::lower($filename) === 'demo.txt'
                ? 'Factory Knowledge Overview'
                : $filename;
            $extension = Str::lower(pathinfo($filename, PATHINFO_EXTENSION));
            $storageFilename = 'demo_'.$this->safeFilename($filename);
            $storagePath = 'documents/'.$storageFilename;

            $contents = file_get_contents($sourcePath);
            if ($contents === false) {
                throw new RuntimeException("Unable to read demo document: {$sourcePath}");
            }

            $sourceUrl = null;
            $sourceType = in_array($extension, ['html', 'htm', 'mhtml'], true) ? 'html' : 'file';
            if ($extension === 'url' && filter_var(trim($contents), FILTER_VALIDATE_URL)) {
                $sourceUrl = trim($contents);
                $sourceType = 'url';
                $storagePath = null;
            } else {
                Storage::disk('local')->put($storagePath, $contents);
            }

            Document::query()->updateOrCreate(
                ['original_filename' => $databaseFilename],
                [
                    'title' => $databaseFilename === 'Factory Knowledge Overview'
                        ? $databaseFilename
                        : pathinfo($filename, PATHINFO_FILENAME),
                    'file_type' => $this->fileType($filename, $sourceType),
                    'storage_path' => $storagePath,
                    'source_url' => $sourceUrl,
                    'source_type' => $sourceType,
                    'doc_type' => $mapping['doc_type'],
                    'department' => $mapping['department'],
                    'status' => 'indexed',
                    'error_message' => null,
                    'uploaded_by' => $qaUser->id,
                    'indexed_at' => now(),
                ],
            );
        }
    }

    /**
     * @return array{doc_type: string, department: string}
     */
    private function mappingFor(string $filename): array
    {
        $name = Str::lower($filename);

        return match (true) {
            Str::contains($name, 'fire-safety-evacuation-sop') => ['doc_type' => 'buyer_requirement', 'department' => 'compliance_sustainability'],
            Str::contains($name, 'chemical-management-msds-guide') => ['doc_type' => 'sop', 'department' => 'chemical_store'],
            Str::contains($name, 'garment-costing-pricing-guide') => ['doc_type' => 'sop', 'department' => 'accounts_finance'],
            Str::contains($name, 'supplier-evaluation-procurement-policy') => ['doc_type' => 'supplier_guideline', 'department' => 'supply_chain_merchandising'],
            Str::contains($name, 'garment-defect-classification-chart') => ['doc_type' => 'quality_manual', 'department' => 'qa'],
            Str::contains($name, 'employee-onboarding-checklist') => ['doc_type' => 'hr_policy', 'department' => 'hr_training'],
            Str::contains($name, 'buyer-audit-preparation-checklist') => ['doc_type' => 'buyer_requirement', 'department' => 'compliance_sustainability'],
            Str::contains($name, 'line-balancing-efficiency-guide') => ['doc_type' => 'sop', 'department' => 'production'],
            Str::contains($name, 'factory-employee-salary-overview') => ['doc_type' => 'hr_policy', 'department' => 'hr_training'],
            Str::contains($name, ['cisa-incident-response', 'nist-sp-800-50']) => ['doc_type' => 'cybersecurity_policy', 'department' => 'it_cybersecurity'],
            Str::contains($name, 'us-dol-fmla-employee-guide') => ['doc_type' => 'hr_policy', 'department' => 'hr_training'],
            Str::contains($name, 'us-epa-40-cfr-part-410') => ['doc_type' => 'compliance_audit', 'department' => 'compliance_sustainability'],
            Str::contains($name, 'production-process-guide') => ['doc_type' => 'production_guideline', 'department' => 'production'],
            Str::contains($name, 'supply-chain-procurement-guide') => ['doc_type' => 'supplier_guideline', 'department' => 'supply_chain_merchandising'],
            Str::contains($name, 'it-cybersecurity-guide') => ['doc_type' => 'cybersecurity_policy', 'department' => 'it_cybersecurity'],
            Str::contains($name, 'production-overview') => ['doc_type' => 'production_guideline', 'department' => 'production'],
            Str::contains($name, 'supply-chain-overview') => ['doc_type' => 'supplier_guideline', 'department' => 'supply_chain_merchandising'],
            Str::contains($name, 'it-cybersecurity-overview') => ['doc_type' => 'cybersecurity_policy', 'department' => 'it_cybersecurity'],
            Str::contains($name, 'spinning-overview') => ['doc_type' => 'production_guideline', 'department' => 'spinning'],
            Str::contains($name, 'weaving-overview') => ['doc_type' => 'production_guideline', 'department' => 'weaving'],
            Str::contains($name, 'knitting-overview') => ['doc_type' => 'production_guideline', 'department' => 'knitting'],
            Str::contains($name, 'dyeing-finishing-overview') => ['doc_type' => 'production_guideline', 'department' => 'dyeing_finishing'],
            Str::contains($name, 'quality-control-overview') => ['doc_type' => 'quality_manual', 'department' => 'quality_control'],
            Str::contains($name, 'ppc-overview') => ['doc_type' => 'production_guideline', 'department' => 'ppc'],
            Str::contains($name, 'industrial-engineering-overview') => ['doc_type' => 'production_guideline', 'department' => 'industrial_engineering'],
            Str::contains($name, 'maintenance-engineering-overview') => ['doc_type' => 'production_guideline', 'department' => 'maintenance_engineering'],
            Str::contains($name, 'store-warehouse-overview') => ['doc_type' => 'supplier_guideline', 'department' => 'store_warehouse'],
            Str::contains($name, 'chemical-store-overview') => ['doc_type' => 'other', 'department' => 'chemical_store'],
            Str::contains($name, 'etp-overview') => ['doc_type' => 'compliance_audit', 'department' => 'etp'],
            Str::contains($name, 'accounts-finance-overview') => ['doc_type' => 'other', 'department' => 'accounts_finance'],
            Str::contains($name, 'commercial-sales-overview') => ['doc_type' => 'buyer_requirement', 'department' => 'commercial_sales'],
            Str::contains($name, 'dyeing') => ['doc_type' => 'production_guideline', 'department' => 'dyeing_finishing'],
            Str::contains($name, 'quality-control') => ['doc_type' => 'quality_manual', 'department' => 'quality_control'],
            Str::contains($name, 'industrial') => ['doc_type' => 'production_guideline', 'department' => 'industrial_engineering'],
            Str::contains($name, 'maintenance') => ['doc_type' => 'production_guideline', 'department' => 'maintenance_engineering'],
            Str::contains($name, 'store-warehouse') => ['doc_type' => 'supplier_guideline', 'department' => 'store_warehouse'],
            Str::contains($name, 'chemical') => ['doc_type' => 'other', 'department' => 'chemical_store'],
            Str::contains($name, 'etp') => ['doc_type' => 'compliance_audit', 'department' => 'etp'],
            Str::contains($name, 'accounts') => ['doc_type' => 'other', 'department' => 'accounts_finance'],
            Str::contains($name, 'commercial') => ['doc_type' => 'buyer_requirement', 'department' => 'commercial_sales'],
            Str::contains($name, 'it-edp') => ['doc_type' => 'cybersecurity_policy', 'department' => 'it_edp'],
            Str::contains($name, 'spinning') => ['doc_type' => 'production_guideline', 'department' => 'spinning'],
            Str::contains($name, 'weaving') => ['doc_type' => 'production_guideline', 'department' => 'weaving'],
            Str::contains($name, 'knitting') => ['doc_type' => 'production_guideline', 'department' => 'knitting'],
            Str::contains($name, 'ppc') => ['doc_type' => 'production_guideline', 'department' => 'ppc'],
            Str::contains($name, ['hr', 'ppe']) => [
                'doc_type' => 'hr_policy',
                'department' => 'hr_training',
            ],
            Str::contains($name, 'aql') => [
                'doc_type' => 'quality_manual',
                'department' => 'qa',
            ],
            Str::contains($name, ['qa', 'bkmea']) => [
                'doc_type' => 'quality_manual',
                'department' => 'qa',
            ],
            Str::contains($name, 'phishing') => [
                'doc_type' => 'cybersecurity_policy',
                'department' => 'it_cybersecurity',
            ],
            Str::contains($name, 'cyber') => [
                'doc_type' => 'cybersecurity_policy',
                'department' => 'it_cybersecurity',
            ],
            Str::contains($name, ['hm', 'compliance']) => [
                'doc_type' => 'buyer_requirement',
                'department' => 'compliance_sustainability',
            ],
            Str::contains($name, ['ilo', 'wcms', 'demo.txt']) => [
                'doc_type' => 'other',
                'department' => 'general',
            ],
            default => ['doc_type' => 'other', 'department' => 'general'],
        };
    }

    private function fileType(string $filename, string $sourceType = 'file'): string
    {
        if ($sourceType === 'url') {
            return 'web_url';
        }

        return match (Str::lower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'pdf',
            'txt' => 'txt',
            'csv' => 'csv',
            'docx' => 'docx',
            'html', 'htm', 'mhtml' => 'html',
            default => 'txt',
        };
    }

    private function safeFilename(string $filename): string
    {
        return Str::uuid()->toString().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    }
}
