<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();

            // Người phụ trách. nullOnDelete: người phụ trách nghỉ việc thì dự án
            // vẫn còn, chỉ trống ô phụ trách để quản lý gán lại.
            $table->foreignId('owner_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()
                ->constrained('departments')->nullOnDelete();

            $table->string('status', 30)->default('planning');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'department_id']);
            $table->index('owner_id');
        });

        Schema::create('project_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('member');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
    }
};
