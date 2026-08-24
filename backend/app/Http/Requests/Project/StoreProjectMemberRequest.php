<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Domain\Task\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectMemberRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', Rule::exists('users', 'uuid')],
            'role' => ['nullable', Rule::enum(ProjectRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'người dùng',
            'role' => 'vai trò trong dự án',
        ];
    }
}
