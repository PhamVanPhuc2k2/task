<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\CreateUserData;
use App\Domain\Identity\Data\CreateUserResult;
use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\Hash;

final class CreateUserAction
{
    public function __construct(
        private readonly RecordUserActivityAction $ghiNhatKy,
    ) {}

    /**
     * @param  User|null  $actor  Null khi lệnh `users:import` gọi — lúc đó
     *                            không có ai đứng sau thao tác.
     */
    public function execute(CreateUserData $data, ?User $actor = null): CreateUserResult
    {
        $user = new User;

        $user->fill([
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'department_id' => $data->departmentId,
            'position_id' => $data->positionId,
            'manager_id' => $data->managerId,
            'joined_at' => $data->joinedAt,
            'is_active' => true,
        ]);

        $user->employee_code = $data->employeeCode;

        // Mật khẩu ngẫu nhiên cho từng người, không bao giờ có mật khẩu mặc
        // định dùng chung: một mật khẩu mặc định là một mật khẩu cả công ty
        // biết, và sẽ có người không bao giờ đổi.
        //
        // Trả về dạng rõ đúng một lần qua CreateUserResult — database chỉ lưu
        // bản băm nên đây là cơ hội duy nhất đọc được nó.
        $temporary = TemporaryPassword::generate();
        $user->password = Hash::make($temporary);

        $user->save();

        $user->assignRole($data->role->value);

        $this->ghiNhatKy->execute(
            user: $user,
            event: UserActivityEvent::Created,
            causer: $actor,
            new: ['role' => $data->role->value, 'employee_code' => $data->employeeCode],
        );

        return new CreateUserResult($user, $temporary);
    }
}
