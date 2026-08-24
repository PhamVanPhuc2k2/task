<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Domain\Task\Data\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:'.AttachmentRules::MAX_PER_REQUEST],

            // `mimetypes` chứ KHÔNG phải `mimes`: luật này đọc nội dung thật
            // của tệp qua finfo, còn `mimes` chỉ nhìn phần mở rộng. Đổi tên
            // `shell.php` thành `anh.jpg` là qua được `mimes`.
            'files.*' => [
                'required',
                'file',
                'max:'.(int) (AttachmentRules::MAX_BYTES / 1024),
                'mimetypes:'.implode(',', AttachmentRules::mimeTypes()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'files' => 'tệp đính kèm',
            'files.*' => 'tệp đính kèm',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.max' => 'Mỗi lần chỉ tải lên tối đa '.AttachmentRules::MAX_PER_REQUEST.' tệp.',
            'files.*.max' => 'Mỗi tệp tối đa 10 MB.',
            'files.*.mimetypes' => 'Định dạng tệp này không được phép đính kèm.',
        ];
    }
}
