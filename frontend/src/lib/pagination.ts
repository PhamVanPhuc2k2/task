/**
 * Hình dạng phân trang mà API Resource của Laravel trả về.
 *
 * Backend dùng phân trang offset thống nhất ở mọi endpoint danh sách — xem
 * README mục 1.4. Khai báo một lần ở đây để mỗi feature không tự định nghĩa
 * lại một kiểu hơi khác nhau.
 */
export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
}

/** Bọc một danh sách phẳng, không phân trang. */
export interface Wrapped<T> {
  data: T;
}
