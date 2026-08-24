<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\ImportUsersResult;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\FileNotReadableException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Nhập danh sách nhân viên từ file CSV.
 *
 * Dùng ở lúc go-live và mỗi khi có đợt tuyển mới. Đối chiếu theo `employee_code`
 * nên chạy lại nhiều lần cũng an toàn: người đã có thì cập nhật, không tạo trùng.
 *
 * Chọn CSV chứ không phải .xlsx để chưa phải kéo PhpSpreadsheet vào dự án.
 * Excel lưu được sang "CSV UTF-8" trong hai cú nhấp. Khi đợt 3 cần xuất bảng
 * công .xlsx cho kế toán thì PhpSpreadsheet sẽ có mặt, lúc đó bổ sung sau.
 *
 * Cột bắt buộc: employee_code, name, email
 * Cột tuỳ chọn: phone, department_code, position_code, manager_email, joined_at
 */
final class ImportUsersFromCsvAction
{
    private const REQUIRED_COLUMNS = ['employee_code', 'name', 'email'];

    public function execute(string $path, bool $dryRun = false): ImportUsersResult
    {
        $rows = $this->readRows($path);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // Nạp sẵn danh mục để không truy vấn lại cho từng dòng.
        $departments = Department::query()->whereNotNull('code')->pluck('id', 'code');
        $positions = Position::query()->whereNotNull('code')->pluck('id', 'code');

        DB::beginTransaction();

        foreach ($rows as $lineNumber => $row) {
            $missing = $this->missingRequiredColumns($row);

            if ($missing !== []) {
                $skipped++;
                $errors[] = sprintf('Dòng %d: thiếu %s', $lineNumber, implode(', ', $missing));

                continue;
            }

            $existing = User::query()
                ->where('employee_code', $row['employee_code'])
                ->first();

            $attributes = [
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $this->nullIfBlank($row['phone'] ?? null),
                'department_id' => $departments[$row['department_code'] ?? ''] ?? null,
                'position_id' => $positions[$row['position_code'] ?? ''] ?? null,
                'manager_id' => $this->resolveManagerId($row['manager_email'] ?? null),
                'joined_at' => $this->nullIfBlank($row['joined_at'] ?? null),
                'is_active' => true,
            ];

            if ($existing instanceof User) {
                $existing->fill($attributes)->save();
                $updated++;

                continue;
            }

            $user = new User;
            $user->fill($attributes);
            $user->employee_code = $row['employee_code'];
            // Mật khẩu ngẫu nhiên: nhân viên nhận tài khoản qua luồng đặt lại
            // mật khẩu, không bao giờ có mật khẩu mặc định dùng chung.
            $user->password = Hash::make(Str::random(32));
            $user->save();

            $created++;
        }

        if ($dryRun) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        return new ImportUsersResult(
            created: $created,
            updated: $updated,
            skipped: $skipped,
            errors: $errors,
        );
    }

    /**
     * @return array<int, array<string, string>> Số dòng trong file => dữ liệu dòng
     */
    private function readRows(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new FileNotReadableException($path);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new FileNotReadableException($path);
        }

        $header = fgetcsv($handle, escape: '');

        if ($header === false) {
            fclose($handle);

            return [];
        }

        // Excel chèn BOM khi lưu "CSV UTF-8". Không gỡ thì tên cột đầu tiên
        // thành "\u{FEFF}employee_code" và mọi dòng đều bị coi là thiếu mã.
        $header[0] = ltrim((string) $header[0], "\u{FEFF}");
        $header = array_map(static fn (mixed $name): string => trim((string) $name), $header);

        $rows = [];
        $lineNumber = 1;

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $lineNumber++;

            if ($values === [null]) {
                continue;
            }

            $values = array_map(static fn (mixed $value): string => trim((string) $value), $values);
            $values = array_pad($values, count($header), '');

            /** @var array<string, string> $row */
            $row = array_combine($header, array_slice($values, 0, count($header)));
            $rows[$lineNumber] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function missingRequiredColumns(array $row): array
    {
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (($row[$column] ?? '') === '') {
                $missing[] = $column;
            }
        }

        return $missing;
    }

    private function resolveManagerId(?string $email): ?int
    {
        if ($email === null || $email === '') {
            return null;
        }

        $id = User::query()->where('email', $email)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function nullIfBlank(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : $value;
    }
}
