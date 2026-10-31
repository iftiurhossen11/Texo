<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documents', 'source_type')) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->string('source_type')->default('file')->after('source_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('documents', 'source_type')) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->dropColumn('source_type');
            });
        }
    }
};
