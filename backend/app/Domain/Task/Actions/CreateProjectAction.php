<?php

declare(strict_types=1);

namespace App\Domain\Task\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Data\CreateProjectData;
use App\Domain\Task\Enums\ProjectRole;
use App\Domain\Task\Models\Project;
use Illuminate\Support\Facades\DB;

final class CreateProjectAction
{
    public function execute(CreateProjectData $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor): Project {
            $project = new Project;

            $project->fill([
                'name' => $data->name,
                'code' => $data->code,
                'description' => $data->description,
                // Không khai chủ dự án thì người tạo chịu trách nhiệm. Dự án
                // không có chủ là dự án không ai chịu trách nhiệm.
                'owner_id' => $data->ownerId ?? $actor->id,
                'department_id' => $data->departmentId ?? $actor->department_id,
                'status' => $data->status,
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
            ]);

            $project->created_by = $actor->id;
            $project->save();

            // Chủ dự án luôn là quản lý trong chính dự án của mình — nếu không,
            // họ tạo xong lại không thấy dự án mình vừa tạo.
            $project->members()->syncWithoutDetaching([
                $project->owner_id => ['role' => ProjectRole::Manager->value],
            ]);

            return $project;
        });
    }
}
