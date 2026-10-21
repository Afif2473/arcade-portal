<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('s3_prefix', 255);
            $table->enum('source', ['zip_upload', 'github_actions']);
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
            $table->unsignedBigInteger('bundle_size')->nullable();
            $table->unsignedInteger('file_count')->nullable();
            $table->char('commit_sha', 40)->nullable();
            $table->string('failure_reason', 512)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['game_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_versions');
    }
};
