<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Thông báo lỗi kiểm tra dữ liệu — tiếng Việt
|--------------------------------------------------------------------------
|
| `APP_LOCALE=vi` đã đặt từ đầu, nhưng thư mục `lang/` chưa từng tồn tại nên
| Laravel rơi về bản tiếng Anh dựng sẵn trong vendor. Kết quả: mọi form trong
| ứng dụng hiện lỗi nửa Việt nửa Anh — tên trường đã dịch qua `attributes()`
| của từng FormRequest, còn câu thì vẫn "The email field is required."
|
| Cách dịch ở đây có hai điểm khác bản dịch phổ biến:
|
|   1. **Xưng hô trung tính, không "bạn".** Đây là phần mềm nội bộ dùng chung
|      cho cả giám đốc lẫn nhân viên mới; câu mệnh lệnh không chủ ngữ đọc gọn
|      và không bị lệch vai.
|
|   2. **Nói phải làm gì, không chỉ nói sai gì.** "Chưa nhập họ tên" thay vì
|      "Trường họ tên là bắt buộc" — người dùng cần biết bước tiếp theo.
|
| Khoá nào thiếu ở đây sẽ tự rơi về `APP_FALLBACK_LOCALE=en`, nên bổ sung dần
| được mà không sợ vỡ.
|
*/

return [
    'accepted' => 'Phải đồng ý :attribute.',
    'accepted_if' => 'Phải đồng ý :attribute khi :other là :value.',
    'active_url' => ':Attribute không phải là địa chỉ hợp lệ.',
    'after' => ':Attribute phải là ngày sau :date.',
    'after_or_equal' => ':Attribute phải là ngày :date trở đi.',
    'alpha' => ':Attribute chỉ được chứa chữ cái.',
    'alpha_dash' => ':Attribute chỉ được chứa chữ cái, số, gạch ngang và gạch dưới.',
    'alpha_num' => ':Attribute chỉ được chứa chữ cái và số.',
    'array' => ':Attribute phải là một danh sách.',
    'ascii' => ':Attribute chỉ được chứa ký tự và ký hiệu không dấu.',
    'before' => ':Attribute phải là ngày trước :date.',
    'before_or_equal' => ':Attribute phải là ngày :date trở về trước.',

    'between' => [
        'array' => ':Attribute phải có từ :min đến :max phần tử.',
        'file' => ':Attribute phải nặng từ :min đến :max KB.',
        'numeric' => ':Attribute phải nằm trong khoảng :min đến :max.',
        'string' => ':Attribute phải dài từ :min đến :max ký tự.',
    ],

    'boolean' => ':Attribute chỉ nhận giá trị đúng hoặc sai.',
    'can' => ':Attribute chứa giá trị không được phép.',
    'confirmed' => ':Attribute nhập lại không khớp.',
    'contains' => ':Attribute thiếu một giá trị bắt buộc.',
    'current_password' => 'Mật khẩu không đúng.',
    'date' => ':Attribute không phải là ngày hợp lệ.',
    'date_equals' => ':Attribute phải đúng bằng ngày :date.',
    'date_format' => ':Attribute phải đúng định dạng :format.',
    'decimal' => ':Attribute phải có :decimal chữ số thập phân.',
    'declined' => 'Phải từ chối :attribute.',
    'declined_if' => 'Phải từ chối :attribute khi :other là :value.',
    'different' => ':Attribute và :other phải khác nhau.',
    'digits' => ':Attribute phải gồm :digits chữ số.',
    'digits_between' => ':Attribute phải gồm từ :min đến :max chữ số.',
    'dimensions' => ':Attribute có kích thước ảnh không hợp lệ.',
    'distinct' => ':Attribute bị trùng giá trị.',
    'doesnt_end_with' => ':Attribute không được kết thúc bằng: :values.',
    'doesnt_start_with' => ':Attribute không được bắt đầu bằng: :values.',
    'email' => ':Attribute không đúng định dạng email.',
    'ends_with' => ':Attribute phải kết thúc bằng một trong: :values.',
    'enum' => ':Attribute chứa giá trị không hợp lệ.',
    'exists' => ':Attribute được chọn không tồn tại.',
    'extensions' => ':Attribute phải có phần mở rộng: :values.',
    'file' => ':Attribute phải là một tệp.',
    'filled' => 'Chưa nhập :attribute.',

    'gt' => [
        'array' => ':Attribute phải có nhiều hơn :value phần tử.',
        'file' => ':Attribute phải nặng hơn :value KB.',
        'numeric' => ':Attribute phải lớn hơn :value.',
        'string' => ':Attribute phải dài hơn :value ký tự.',
    ],

    'gte' => [
        'array' => ':Attribute phải có ít nhất :value phần tử.',
        'file' => ':Attribute phải nặng ít nhất :value KB.',
        'numeric' => ':Attribute phải từ :value trở lên.',
        'string' => ':Attribute phải dài ít nhất :value ký tự.',
    ],

    'hex_color' => ':Attribute phải là mã màu hợp lệ.',
    'image' => ':Attribute phải là ảnh.',
    'in' => ':Attribute chứa giá trị không hợp lệ.',
    'in_array' => ':Attribute không có trong :other.',
    'integer' => ':Attribute phải là số nguyên.',
    'ip' => ':Attribute phải là địa chỉ IP hợp lệ.',
    'ipv4' => ':Attribute phải là địa chỉ IPv4 hợp lệ.',
    'ipv6' => ':Attribute phải là địa chỉ IPv6 hợp lệ.',
    'json' => ':Attribute phải là chuỗi JSON hợp lệ.',
    'list' => ':Attribute phải là một danh sách.',
    'lowercase' => ':Attribute phải viết thường toàn bộ.',

    'lt' => [
        'array' => ':Attribute phải có ít hơn :value phần tử.',
        'file' => ':Attribute phải nhẹ hơn :value KB.',
        'numeric' => ':Attribute phải nhỏ hơn :value.',
        'string' => ':Attribute phải ngắn hơn :value ký tự.',
    ],

    'lte' => [
        'array' => ':Attribute không được quá :value phần tử.',
        'file' => ':Attribute không được nặng quá :value KB.',
        'numeric' => ':Attribute không được lớn hơn :value.',
        'string' => ':Attribute không được dài quá :value ký tự.',
    ],

    'mac_address' => ':Attribute phải là địa chỉ MAC hợp lệ.',

    'max' => [
        'array' => ':Attribute không được quá :max phần tử.',
        'file' => ':Attribute không được nặng quá :max KB.',
        'numeric' => ':Attribute không được lớn hơn :max.',
        'string' => ':Attribute không được dài quá :max ký tự.',
    ],

    'max_digits' => ':Attribute không được quá :max chữ số.',
    'mimes' => ':Attribute phải là tệp thuộc loại: :values.',
    'mimetypes' => ':Attribute phải là tệp thuộc loại: :values.',

    'min' => [
        'array' => ':Attribute phải có ít nhất :min phần tử.',
        'file' => ':Attribute phải nặng ít nhất :min KB.',
        'numeric' => ':Attribute phải từ :min trở lên.',
        'string' => ':Attribute phải dài ít nhất :min ký tự.',
    ],

    'min_digits' => ':Attribute phải có ít nhất :min chữ số.',
    'missing' => 'Không được gửi :attribute.',
    'missing_if' => 'Không được gửi :attribute khi :other là :value.',
    'missing_unless' => 'Không được gửi :attribute trừ khi :other là :value.',
    'missing_with' => 'Không được gửi :attribute khi đã có :values.',
    'missing_with_all' => 'Không được gửi :attribute khi đã có :values.',
    'multiple_of' => ':Attribute phải là bội số của :value.',
    'not_in' => ':Attribute chứa giá trị không được phép.',
    'not_regex' => ':Attribute có định dạng không hợp lệ.',
    'numeric' => ':Attribute phải là số.',

    'password' => [
        'letters' => ':Attribute phải có ít nhất một chữ cái.',
        'mixed' => ':Attribute phải có cả chữ hoa và chữ thường.',
        'numbers' => ':Attribute phải có ít nhất một chữ số.',
        'symbols' => ':Attribute phải có ít nhất một ký tự đặc biệt.',
        // Câu này nói rõ vì sao bị từ chối: người dùng thường tưởng mật khẩu
        // của họ bị chê yếu, trong khi thực tế nó đã lộ trong một vụ rò rỉ.
        'uncompromised' => ':Attribute đã từng xuất hiện trong một vụ lộ dữ liệu. Chọn mật khẩu khác.',
    ],

    'present' => 'Thiếu :attribute.',
    'present_if' => 'Thiếu :attribute khi :other là :value.',
    'present_unless' => 'Thiếu :attribute trừ khi :other là :value.',
    'present_with' => 'Thiếu :attribute khi đã có :values.',
    'present_with_all' => 'Thiếu :attribute khi đã có :values.',
    'prohibited' => 'Không được gửi :attribute.',
    'prohibited_if' => 'Không được gửi :attribute khi :other là :value.',
    'prohibited_if_accepted' => 'Không được gửi :attribute khi đã đồng ý :other.',
    'prohibited_if_declined' => 'Không được gửi :attribute khi đã từ chối :other.',
    'prohibited_unless' => 'Không được gửi :attribute trừ khi :other thuộc :values.',
    'prohibits' => ':Attribute khiến :other không được phép gửi.',
    'regex' => ':Attribute có định dạng không hợp lệ.',

    // Câu gặp nhiều nhất trong cả hệ thống. "Chưa nhập" thay vì "là bắt buộc":
    // ngắn hơn, và nói đúng việc người dùng cần làm tiếp.
    'required' => 'Chưa nhập :attribute.',

    'required_array_keys' => ':Attribute phải có các mục: :values.',
    'required_if' => 'Chưa nhập :attribute khi :other là :value.',
    'required_if_accepted' => 'Chưa nhập :attribute khi đã đồng ý :other.',
    'required_if_declined' => 'Chưa nhập :attribute khi đã từ chối :other.',
    'required_unless' => 'Chưa nhập :attribute trừ khi :other thuộc :values.',
    'required_with' => 'Chưa nhập :attribute khi đã có :values.',
    'required_with_all' => 'Chưa nhập :attribute khi đã có :values.',
    'required_without' => 'Chưa nhập :attribute khi chưa có :values.',
    'required_without_all' => 'Chưa nhập :attribute khi chưa có :values.',
    'same' => ':Attribute phải giống :other.',

    'size' => [
        'array' => ':Attribute phải có đúng :size phần tử.',
        'file' => ':Attribute phải nặng đúng :size KB.',
        'numeric' => ':Attribute phải bằng :size.',
        'string' => ':Attribute phải dài đúng :size ký tự.',
    ],

    'starts_with' => ':Attribute phải bắt đầu bằng một trong: :values.',
    'string' => ':Attribute phải là chuỗi ký tự.',
    'timezone' => ':Attribute phải là múi giờ hợp lệ.',

    // Câu gặp nhiều thứ hai. Nói rõ "đã có người dùng" thay vì "đã được sử
    // dụng" — người nhập cần hiểu là trùng với người khác, không phải lỗi hệ
    // thống.
    'unique' => ':Attribute này đã có người dùng.',

    'uploaded' => 'Tải :attribute lên thất bại. Thử lại hoặc chọn tệp nhỏ hơn.',
    'uppercase' => ':Attribute phải viết hoa toàn bộ.',
    'url' => ':Attribute phải là địa chỉ web hợp lệ.',
    'ulid' => ':Attribute phải là ULID hợp lệ.',
    'uuid' => ':Attribute không hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Thông báo riêng cho từng trường
    |--------------------------------------------------------------------------
    |
    | Để trống có chủ ý. Thông báo riêng của từng màn hình nằm ở `messages()`
    | của chính FormRequest đó — đặt cạnh luật nó phục vụ thì người sửa luật sẽ
    | thấy ngay, còn gom hết vào đây thì một năm sau không ai biết dòng nào còn
    | được dùng.
    |
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Tên trường dùng chung
    |--------------------------------------------------------------------------
    |
    | Lưới an toàn cho những trường mà FormRequest quên khai trong
    | `attributes()`. Bản đầu tôi để trống chỗ này với lập luận "mỗi FormRequest
    | tự khai" — và chạy thật thì lộ ngay: `StoreUserRequest` khai bảy trường
    | nhưng bỏ sót `phone`, nên người dùng nhận câu *"phone không được dài quá
    | 20 ký tự."* Bắt mọi FormRequest nhớ mọi trường là điều sẽ hỏng, chỉ là
    | chưa biết hỏng ở đâu.
    |
    | FormRequest vẫn ghi đè được khi cùng một cột mang tên khác nhau ở hai màn
    | hình ("người thực hiện" ở giao việc, "nhân viên" ở chia thưởng).
    |
    | Viết thường: các câu ở trên dùng `:Attribute` nên Laravel tự viết hoa chữ
    | đầu khi placeholder đứng đầu câu.
    |
    */

    'attributes' => [
        // Nhân sự
        'name' => 'họ tên',
        'email' => 'email',
        'password' => 'mật khẩu',
        'password_confirmation' => 'xác nhận mật khẩu',
        'current_password' => 'mật khẩu hiện tại',
        'phone' => 'số điện thoại',
        'employee_code' => 'mã nhân viên',
        'joined_at' => 'ngày vào làm',
        'role' => 'vai trò',
        'department_id' => 'phòng ban',
        'position_id' => 'chức vụ',
        'manager_id' => 'quản lý trực tiếp',

        // Công việc & dự án
        'title' => 'tiêu đề',
        'description' => 'mô tả',
        'status' => 'trạng thái',
        'priority' => 'mức ưu tiên',
        'due_date' => 'hạn hoàn thành',
        'assignee_id' => 'người thực hiện',
        'reviewer_id' => 'người duyệt',
        'project_id' => 'dự án',
        'estimate_hours' => 'số giờ ước tính',
        'progress_percent' => 'phần trăm hoàn thành',
        'start_date' => 'ngày bắt đầu',
        'end_date' => 'ngày kết thúc',
        'code' => 'mã',
        'reason' => 'lý do',
        'body' => 'nội dung',
        'parent_id' => 'bình luận cha',

        // Chấm công, lương, thưởng
        'work_date' => 'ngày công',
        'decision' => 'quyết định',
        'adjusted_minutes' => 'số phút ấn định',
        'base_salary' => 'lương cơ bản',
        'allowance' => 'phụ cấp',
        'effective_from' => 'ngày hiệu lực',
        'total_amount' => 'tổng quỹ',
        'condition_note' => 'điều kiện mở quỹ',
        'amount' => 'số tiền',

        // Chung
        'month' => 'tháng',
        'search' => 'từ khoá tìm kiếm',
        'page' => 'trang',
        'per_page' => 'số dòng mỗi trang',
        'code_2fa' => 'mã xác thực',
        'recovery_code' => 'mã khôi phục',
    ],
];
