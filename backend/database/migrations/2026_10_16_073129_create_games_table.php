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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained('users')->restrictOnDelete();
            $table->string('slug', 96)->unique();
            $table->string('title', 128);
            $table->string('short_description', 280)->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail_path', 512)->nullable();
            $table->string('aspect_ratio', 10)->default('16:9');
            $table->enum('status', ['draft', 'sandbox', 'published'])->default('draft');
            $table->enum('score_sort', ['desc', 'asc'])->default('desc');
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('developer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
