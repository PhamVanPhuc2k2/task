<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xác thực hai lớp bằng mã OTP (TOTP).
 *
 * Bắt buộc với toàn bộ nhân viên: không thiết lập thì không đăng nhập được.
 *
 * `two_factor_secret` và `two_factor_recovery_codes` được mã hoá ở tầng model
 * (cast `encrypted`). Ai đọc được dump database cũng không dựng lại được mã —
 * secret TOTP mà lộ thì lớp thứ hai coi như không tồn tại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');

            // Chỉ được coi là đã bật khi người dùng nhập đúng mã lần đầu.
            // Sinh secret thôi chưa đủ: nếu họ quét QR hỏng mà ta đã bật rồi
            // thì họ bị khoá ngoài ngay lập tức.
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
