<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Cây phòng ban. restrictOnDelete: không cho xoá cứng một phòng ban
            // còn phòng con — phải xử lý cây trước, không để mồ côi.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('departments')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
