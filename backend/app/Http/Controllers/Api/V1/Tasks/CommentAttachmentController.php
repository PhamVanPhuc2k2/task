<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Task\Models\TaskComment;
use App\Http\Requests\Task\StoreAttachmentRequest;
use App\Http\Resources\TaskCommentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Tệp đính kèm của một bình luận.
 *
 * Tách khỏi endpoint viết bình luận vì hai lời gọi khác nhau về bản chất: một
 * cái gửi JSON, một cái gửi multipart. Trộn vào nhau thì mọi client phải dựng
 * multipart cho cả những bình luận không có tệp nào.
 *
 * Quyền đi theo quyền sửa bình luận: chỉ tác giả mới đính kèm hoặc gỡ tệp trên
 * bình luận của mình.
 */
final class CommentAttachmentController
{
    #[Authorize('update', 'comment')]
    public function store(
        StoreAttachmentRequest $request,
        TaskComment $comment,
    ): JsonResponse {
        /** @var list<UploadedFile> $files */
        $files = $request->file('files');

        foreach ($files as $file) {
            $comment
                ->addMedia($file)
                // Tên hiển thị giữ nguyên tên gốc để người dùng nhận ra tệp...
                ->usingName($this->tenHienThi($file))
                // ...nhưng tên trên đĩa là chuỗi ngẫu nhiên. Giữ tên gốc là mở
                // đường cho traversal, ký tự điều khiển, và trùng tên giữa hai
                // người tải cùng lúc.
                ->usingFileName(Str::uuid()->toString().'.'.$this->duoiAnToan($file))
                ->toMediaCollection(TaskComment::DINH_KEM);
        }

        return TaskCommentResource::make(
            $comment->load(['author', 'mentions', 'media']),
        )->response()->setStatusCode(Response::HTTP_CREATED);
    }

    #[Authorize('update', 'comment')]
    public function destroy(TaskComment $comment, string $media): JsonResponse
    {
        // Tìm trong đúng bình luận này, không tìm toàn bảng: nếu không, ai
        // cũng xoá được tệp của bình luận khác chỉ bằng cách đoán uuid.
        $tep = $comment->getMedia(TaskComment::DINH_KEM)
            ->first(fn (Media $m): bool => $m->uuid === $media);

        abort_if($tep === null, Response::HTTP_NOT_FOUND);

        $tep->delete();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Tên gốc, đã cắt bớt và bỏ ký tự điều khiển trước khi lưu vào database. */
    private function tenHienThi(UploadedFile $file): string
    {
        $ten = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ten = preg_replace('/[\x00-\x1F\x7F]/u', '', $ten) ?? '';
        $ten = trim($ten);

        return $ten === '' ? 'tep-dinh-kem' : Str::limit($ten, 100, '');
    }

    /**
     * Phần mở rộng suy từ kiểu MIME thật, không lấy từ tên người dùng đặt.
     *
     * `guessExtension()` đọc nội dung tệp; `getClientOriginalExtension()` chỉ
     * đọc chuỗi client gửi lên và tin được đúng bằng không.
     */
    private function duoiAnToan(UploadedFile $file): string
    {
        $duoi = $file->guessExtension() ?? 'bin';

        return preg_replace('/[^a-z0-9]/i', '', $duoi) ?: 'bin';
    }
}
