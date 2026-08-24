<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->string('name');
            $table->string('code')->nullable()->unique();

            // Cấp bậc: số càng lớn càng cao. Dùng để sắp xếp danh sách và về
            // sau để suy ra luồng duyệt mặc định (đơn nghỉ phép lên cấp trên).
            $table->unsignedTinyInteger('level')->default(1);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
