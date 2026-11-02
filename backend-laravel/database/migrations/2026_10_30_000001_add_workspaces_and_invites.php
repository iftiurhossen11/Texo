<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('primary_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained('workspaces')->restrictOnDelete();
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained('workspaces')->restrictOnDelete();
        });

        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->after('workspace_id')->constrained('users')->nullOnDelete();
        });

        $now = now();
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Texo Workspace',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->update(['workspace_id' => $workspaceId]);
        DB::table('documents')->update(['workspace_id' => $workspaceId]);
        DB::table('chat_sessions')->update(['workspace_id' => $workspaceId]);

        Schema::create('workspace_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('role', 32);
            $table->unsignedSmallInteger('uses_remaining')->default(1);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invites');

        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::dropIfExists('workspaces');
    }
};
