# Frontend — Next.js 16

Giao diện của hệ thống quản lý công việc. Toàn bộ tài liệu dự án nằm ở [README gốc](../README.md).

## Lệnh thường dùng

```bash
npm run dev          # chạy dev server ở http://localhost:3000
npm run build        # build production
npm run check        # eslint + prettier + tsc — chạy trước khi tạo pull request
npm run lint:fix     # tự sửa lỗi eslint
npm run format       # tự định dạng bằng prettier
npm run api:types    # sinh src/types/api.ts từ OpenAPI của backend
```

## Quy ước

Xem chương [Cấu trúc frontend](../README.md#cấu-trúc-frontend-nextjs) ở README gốc. Tóm tắt:

- Chia theo **tính năng** (`src/features/`), không chia theo loại file
- Dữ liệu máy chủ do **TanStack Query** quản lý, không nhét vào global store
- Mọi lời gọi API đi qua `src/lib/api-client.ts`, không gọi `fetch()` trực tiếp trong component
- `src/components/ui/` phải thuần giao diện — ESLint chặn nếu import feature hoặc api-client
- Kiểu dữ liệu API **sinh tự động** từ OpenAPI, không gõ tay
- TypeScript strict, cấm `any`
