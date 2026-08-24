/**
 * Ghép class có điều kiện.
 *
 * Viết tay thay vì kéo `clsx`: cả nhu cầu của dự án gói gọn trong sáu dòng,
 * và mỗi package thêm vào là một thứ nữa phải theo dõi bản vá bảo mật.
 */
export type ClassValue =
  string | number | false | null | undefined | ClassValue[];

export function cn(...values: ClassValue[]): string {
  const ra: string[] = [];

  for (const value of values) {
    if (!value) continue;

    if (Array.isArray(value)) {
      const long = cn(...value);
      if (long) ra.push(long);
    } else {
      ra.push(String(value));
    }
  }

  return ra.join(" ");
}
