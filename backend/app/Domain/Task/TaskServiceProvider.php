<?php

declare(strict_types=1);

namespace App\Domain\Task;

use App\Domain\Task\Events\CommentPosted;
use App\Domain\Task\Events\TaskAssigned;
use App\Domain\Task\Listeners\SendCommentNotifications;
use App\Domain\Task\Listeners\SendTaskAssignedNotification;
use App\Domain\Task\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use App\Domain\Task\Observers\TaskActivityObserver;
use App\Policies\ProjectPolicy;
use App\Policies\TaskCommentPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * Miền Task: công việc, giao việc, bình luận, deadline.
 *
 * Nơi đăng ký binding, policy và event listener của riêng miền này.
 * Thêm miền mới = thêm một thư mục và một dòng trong bootstrap/providers.php.
 */
final class TaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Laravel tự dò policy theo quy ước App\Models\X → App\Policies\XPolicy.
        // Model của dự án nằm ở App\Domain\{Miền}\Models nên phải khai báo tay.
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(TaskComment::class, TaskCommentPolicy::class);

        // Nhật ký thay đổi sinh tự động, không phụ thuộc lập trình viên nhớ gọi.
        Task::observe(TaskActivityObserver::class);

        // Khai báo tay thay vì dựa vào tự dò: Laravel chỉ quét App\Listeners,
        // còn listener của dự án nằm trong từng miền.
        Event::listen(TaskAssigned::class, SendTaskAssignedNotification::class);
        Event::listen(CommentPosted::class, SendCommentNotifications::class);

        // Gom mọi job thông báo vào hàng đợi riêng.
        //
        // Không có dòng này thì một đợt quét deadline gửi vài trăm email sẽ
        // nằm chung hàng với job sinh thumbnail và mọi thứ khác — thứ nào tới
        // trước chạy trước, và người bấm "giao việc" phải chờ sau cả đợt quét.
        Queue::route(SendQueuedNotifications::class, 'notifications');
    }
}
