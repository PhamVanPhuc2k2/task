<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mã OTP dùng một lần gửi qua email.
 *
 * Lưu bản BĂM chứ không lưu mã gốc: đây là thông tin xác thực, ai đọc được
 * dump database không được phép đăng nhập thay người khác.
 *
 * Mỗi lần gửi mã mới sẽ vô hiệu hoá toàn bộ mã cũ chưa dùng của người đó —
 * nếu không, mọi mã từng gửi đều còn sống tới khi hết hạn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('code_hash');

            // Nơi mã được gửi tới. Lưu lại để tra được về sau khi người dùng
            // đổi email mà vẫn thắc mắc "mã gửi đi đâu".
            $table->string('sent_to');

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            // Job dọn mã hết hạn quét theo cột này.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_codes');
    }
};
