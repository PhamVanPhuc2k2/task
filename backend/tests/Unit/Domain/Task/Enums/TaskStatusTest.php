<?php

declare(strict_types=1);

use App\Domain\Task\Enums\TaskStatus;

it('không cho nhảy thẳng từ chưa bắt đầu sang hoàn thành', function (): void {
    expect(TaskStatus::Todo->canTransitionTo(TaskStatus::Done))->toBeFalse();
});

it('cho phép luồng bình thường: chưa bắt đầu → đang làm → chờ duyệt → hoàn thành', function (): void {
    expect(TaskStatus::Todo->canTransitionTo(TaskStatus::InProgress))->toBeTrue()
        ->and(TaskStatus::InProgress->canTransitionTo(TaskStatus::Review))->toBeTrue()
        ->and(TaskStatus::Review->canTransitionTo(TaskStatus::Done))->toBeTrue();
});

it('cho phép trả task từ chờ duyệt về đang làm khi quản lý không duyệt', function (): void {
    expect(TaskStatus::Review->canTransitionTo(TaskStatus::InProgress))->toBeTrue();
});

it('coi hoàn thành và đã huỷ là trạng thái kết thúc', function (TaskStatus $status): void {
    expect($status->isClosed())->toBeTrue()
        ->and($status->allowedTransitions())->toBeEmpty();
})->with([
    TaskStatus::Done,
    TaskStatus::Cancelled,
]);

it('có nhãn tiếng Việt cho mọi trạng thái', function (): void {
    foreach (TaskStatus::cases() as $status) {
        expect($status->label())->not->toBeEmpty();
    }
});
