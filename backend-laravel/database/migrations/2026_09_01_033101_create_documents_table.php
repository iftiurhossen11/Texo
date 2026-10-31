<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Charset/collation inherits utf8mb4 / utf8mb4_unicode_ci from config/database.php.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('original_filename');
            $table->enum('file_type', ['pdf', 'txt', 'csv', 'docx', 'html', 'web_url']);
            $table->string('storage_path')->nullable();
            $table->string('source_url')->nullable();
            $table->enum('doc_type', [
                'sop',
                'quality_manual',
                'buyer_requirement',
                'compliance_audit',
                'production_guideline',
                'hr_policy',
                'cybersecurity_policy',
                'supplier_guideline',
                'other',
            ]);
            $table->enum('department', [
                'production',
                'qa',
                'compliance_sustainability',
                'supply_chain_merchandising',
                'hr_training',
                'it_cybersecurity',
                'spinning',
                'weaving',
                'knitting',
                'dyeing_finishing',
                'quality_control',
                'ppc',
                'industrial_engineering',
                'maintenance_engineering',
                'store_warehouse',
                'chemical_store',
                'etp',
                'accounts_finance',
                'commercial_sales',
                'it_edp',
                'general',
            ])->nullable();
            $table->enum('status', ['uploaded', 'processing', 'indexed', 'failed'])->default('uploaded');
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('indexed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
