<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Chuyển mọi ngoại lệ thành một dạng JSON duy nhất cho toàn bộ API:
 *
 *     {
 *       "message": "Thông báo hiển thị được cho người dùng",
 *       "code": "MA_LOI_NOI_BO",
 *       "errors": { "field": ["..."] }      // chỉ có khi là lỗi validate
 *     }
 *
 * Đây là hợp đồng với frontend — `src/lib/api-client.ts` phía Next.js phụ
 * thuộc vào đúng ba khoá này. Đổi dạng ở đây là đụng vào mọi màn hình.
 *
 * Xem README mục 1.4, "Chuẩn chung".
 */
final class ApiExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->json(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Dữ liệu gửi lên chưa hợp lệ.',
                'VALIDATION_FAILED',
                $e->errors(),
            ),

            // Ngoại lệ nghiệp vụ của chính dự án tự khai mã và mã HTTP của nó.
            $e instanceof DomainException => $this->json(
                $e->httpStatus(),
                $e->getMessage(),
                $e->errorCode(),
                $e->fieldErrors() === [] ? null : $e->fieldErrors(),
            ),

            $e instanceof AuthenticationException => $this->json(
                Response::HTTP_UNAUTHORIZED,
                'Bạn cần đăng nhập để tiếp tục.',
                'UNAUTHENTICATED',
            ),

            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => $this->json(
                Response::HTTP_FORBIDDEN,
                'Bạn không có quyền thực hiện thao tác này.',
                'FORBIDDEN',
            ),

            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => $this->json(
                Response::HTTP_NOT_FOUND,
                'Không tìm thấy dữ liệu bạn yêu cầu.',
                'NOT_FOUND',
            ),

            // Tệp qua được validate nhưng bị tầng lưu trữ từ chối — thường là
            // do kiểu MIME thật khác kiểu client khai. Đây là lỗi của dữ liệu
            // gửi lên, không phải lỗi máy chủ, nên phải là 422 chứ không phải
            // 500: người dùng cần biết chọn tệp khác, còn 500 thì họ bấm lại
            // mãi mà không hiểu vì sao.
            $e instanceof FileUnacceptableForCollection => $this->json(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Tệp này không được phép đính kèm.',
                'ATTACHMENT_REJECTED',
                ['files' => ['Tệp này không được phép đính kèm.']],
            ),

            $e instanceof FileIsTooBig => $this->json(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Tệp vượt quá dung lượng cho phép.',
                'ATTACHMENT_TOO_BIG',
                ['files' => ['Mỗi tệp tối đa 10 MB.']],
            ),

            $e instanceof TooManyRequestsHttpException => $this->json(
                Response::HTTP_TOO_MANY_REQUESTS,
                'Bạn thao tác quá nhanh. Vui lòng thử lại sau ít phút.',
                'TOO_MANY_ATTEMPTS',
            ),

            $e instanceof HttpExceptionInterface => $this->json(
                $e->getStatusCode(),
                $e->getMessage() !== '' ? $e->getMessage() : 'Yêu cầu không xử lý được.',
                'HTTP_ERROR',
            ),

            default => $this->renderUnexpected($e),
        };
    }

    /**
     * Lỗi không lường trước.
     *
     * Không bao giờ lộ thông báo gốc ra ngoài production: nó thường chứa đường
     * dẫn file, tên bảng, đôi khi cả dữ liệu. Chi tiết nằm ở log — xem README
     * mục 1.9 Bảo mật.
     */
    private function renderUnexpected(Throwable $e): JsonResponse
    {
        return $this->json(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            config('app.debug') === true
                ? $e->getMessage()
                : 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại hoặc báo bộ phận kỹ thuật.',
            'INTERNAL_ERROR',
        );
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    private function json(int $status, string $message, string $code, ?array $errors = null): JsonResponse
    {
        return new JsonResponse(
            array_filter([
                'message' => $message,
                'code' => $code,
                'errors' => $errors,
            ], static fn (mixed $value): bool => $value !== null),
            $status,
        );
    }
}
