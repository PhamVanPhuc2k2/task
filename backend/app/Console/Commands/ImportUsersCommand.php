<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Actions\ImportUsersFromCsvAction;
use App\Support\Exceptions\FileNotReadableException;
use Illuminate\Console\Command;

/**
 * Lệnh chỉ điều phối: nhận tham số, gọi Action, in kết quả.
 * Toàn bộ nghiệp vụ nằm ở ImportUsersFromCsvAction.
 */
final class ImportUsersCommand extends Command
{
    protected $signature = 'users:import
                            {file : Đường dẫn tới file CSV}
                            {--dry-run : Chạy thử, không ghi gì vào database}';

    protected $description = 'Nhập danh sách nhân viên từ file CSV (Excel lưu dạng "CSV UTF-8")';

    public function handle(ImportUsersFromCsvAction $action): int
    {
        $file = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Chế độ chạy thử — sẽ không ghi gì vào database.');
        }

        try {
            $result = $action->execute($file, $dryRun);
        } catch (FileNotReadableException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(sprintf('Tạo mới: %d', $result->created));
        $this->line(sprintf('Cập nhật: %d', $result->updated));
        $this->line(sprintf('Bỏ qua: %d', $result->skipped));

        if ($result->errors !== []) {
            $this->newLine();
            $this->warn('Các dòng bị bỏ qua:');

            foreach ($result->errors as $error) {
                $this->line('  '.$error);
            }
        }

        return self::SUCCESS;
    }
}
