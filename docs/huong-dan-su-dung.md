# Explus — Hướng dẫn sử dụng

Dành cho nhân viên. Đọc một lượt mất khoảng năm phút.

Nếu gặp vấn đề không có trong tài liệu này, báo bộ phận kỹ thuật — đừng tự
xoay xở, vì phần lớn vấn đề đăng nhập là do cấu hình chứ không phải do bạn.

---

## 1. Đăng nhập

Explus **bắt buộc xác thực hai lớp với mọi tài khoản**. Biết mật khẩu thôi chưa
vào được — đây là chủ ý, vì hệ thống lưu dữ liệu nhân sự.

1. Mở địa chỉ Explus của công ty, nhập **email công ty** và **mật khẩu**.
2. Hệ thống gửi một **mã sáu số** vào chính hộp thư đó. Mở mail, lấy mã.
3. Nhập mã. Ô nhập tự nhảy sang ô kế tiếp, và bạn dán cả sáu số một lúc cũng
   được.
4. Lần đầu đăng nhập, hệ thống hiện **danh sách mã khôi phục**. Chép và cất ở
   nơi an toàn (trình quản lý mật khẩu, hoặc giấy cất trong ngăn kéo có khoá).

> **Mã khôi phục dùng khi nào?** Khi bạn không mở được hộp thư — mất điện
> thoại, mất quyền truy cập email. Mỗi mã dùng được **một lần**. Hết mã thì
> phải nhờ quản trị viên gỡ xác thực hai lớp và thiết lập lại.

### Không nhận được mã?

- Kiểm tra thư mục **Spam**.
- Bấm **Gửi lại mã** — nút có đếm ngược để tránh gửi dồn.
- Mã có hạn vài phút. Quá hạn thì xin mã mới, đừng nhập mã cũ.

### Vài lưu ý về mật khẩu

- Tối thiểu **12 ký tự**, có cả chữ và số.
- Hệ thống từ chối những mật khẩu đã từng bị lộ trong các vụ rò rỉ dữ liệu.
  Nếu bị từ chối, không phải mật khẩu của bạn yếu — mà là nó đã nằm trong
  danh sách công khai ở đâu đó.
- **Explus không bao giờ hỏi mã OTP của bạn qua điện thoại hay tin nhắn.** Ai
  hỏi cũng là lừa đảo.

### Bị đăng xuất giữa chừng

Sau **hai tiếng không thao tác**, phiên tự hết hạn. Đăng nhập lại là xong,
không mất dữ liệu đã lưu.

---

## 2. Màn hình đầu tiên: "Hôm nay của tôi"

Đây là trang mở ra ngay sau khi đăng nhập. Việc của bạn được chia làm bốn nhóm
theo **hạn**, không theo trạng thái:

| Nhóm | Nghĩa là |
|---|---|
| **Quá hạn** | Đã qua hạn mà chưa đóng. Xử lý trước tiên |
| **Hôm nay** | Phải xong trong hôm nay |
| **Tuần này** | Hạn trước hết chủ nhật |
| **Xa hơn** | Hạn sau tuần này, hoặc chưa đặt hạn |

Nhóm **Quá hạn** viền đỏ khi thật sự có việc trễ. Không có việc trễ thì không
có viền — để khi nó đỏ, bạn biết là thật.

---

## 3. Làm việc với một công việc

### Các trạng thái

```
Chưa bắt đầu ──► Đang làm ──► Chờ duyệt ──► Hoàn thành
      │              │             │
      └──────────────┴─────────────┴──► Tạm dừng / Đã huỷ
```

Hệ thống **không cho nhảy bừa**: một việc chưa bắt đầu thì không thể nhảy thẳng
sang hoàn thành. Nếu ô "Chuyển sang…" không có lựa chọn bạn muốn, nghĩa là bước
đó không hợp lệ từ trạng thái hiện tại.

### Đổi trạng thái

Có ba cách, đều dẫn tới cùng một chỗ:

- Trong trang chi tiết việc: ô **"Chuyển sang…"**.
- Trên **Bảng Kanban**: kéo thẻ sang cột khác.
- Trên **Bảng Kanban** với điện thoại: ô **"Chuyển sang…"** ngay dưới mỗi thẻ.
  Kéo thả trên màn hình nhỏ rất khó trúng, nên luôn có đường này.

### Dời hạn

Dời hạn **bắt buộc kèm lý do**, và lý do được lưu lại công khai. Số lần dời hạn
hiện ngay trên trang chi tiết việc.

Đây không phải để làm khó ai. Toàn bộ đánh giá đúng hạn về sau dựa trên
deadline — nếu ai cũng lặng lẽ dời hạn khi sắp trễ thì mọi con số đều vô nghĩa,
kể cả con số của người làm tốt.

### Trao đổi và đính kèm

- Gõ **`@`** rồi vài chữ tên để nhắc đồng nghiệp. Chọn trong danh sách gợi ý —
  đừng gõ tay tên, hệ thống chỉ nhận người bạn chọn từ danh sách.
- **Nhắc tên đồng nghĩa với chia sẻ**: người được nhắc sẽ mở được việc đó, kể
  cả khi họ không thuộc phòng ban liên quan. Cân nhắc trước khi nhắc người
  ngoài nhóm.
- Đính kèm được ảnh, PDF, Word, Excel, văn bản, ZIP. **Tối đa 10 MB mỗi tệp,
  5 tệp mỗi lần.**
- Sửa bình luận của mình được, và bình luận đã sửa sẽ có nhãn *"đã sửa"*. Không
  ai sửa được lời của người khác — kể cả cấp trên.

---

## 4. Thông báo

Chuông ở góc trên bên phải hiện số thông báo chưa đọc.

Năm loại, mặc định như sau:

| Loại | Trong ứng dụng | Email |
|---|---|---|
| Được giao việc | ✅ | ✅ |
| Việc đã quá hạn | ✅ | ✅ |
| Được nhắc tên | ✅ | ✅ |
| Việc sắp tới hạn | ✅ | ❌ |
| Có bình luận mới | ✅ | ❌ |

Đổi trong **Thông báo → Cài đặt thông báo**. Thay đổi lưu ngay, không có nút
"Lưu".

Hệ thống quét deadline **mỗi giờ trong giờ hành chính, ngày làm việc**. Mỗi
việc chỉ nhắc một lần cho mỗi mốc — bạn sẽ không bị nhắc chín lần một ngày về
cùng một việc.

---

## 5. Dùng trên điện thoại

Explus chạy trong trình duyệt điện thoại, không cần cài từ chợ ứng dụng.

**Cài lên màn hình chính:**
- **Android (Chrome):** menu ⋮ → *Thêm vào Màn hình chính*
- **iPhone (Safari):** nút Chia sẻ → *Thêm vào MH chính*

Sau đó Explus mở toàn màn hình như một ứng dụng thường.

> **Chưa dùng được khi mất mạng.** Đây là chủ ý: thà báo rõ không có mạng còn
> hơn hiện danh sách việc của hôm kia và để bạn tưởng đã xong hết.

---

## 6. Câu hỏi hay gặp

**Tôi không thấy việc của đồng nghiệp phòng khác.**
Đúng như thiết kế. Mỗi người chỉ thấy việc của mình, việc mình giao, việc mình
theo dõi, và — nếu là quản lý — việc của phòng mình cùng các phòng trực thuộc.

**Tôi xoá nhầm một việc.**
Xoá là **xoá mềm**: dữ liệu vẫn còn trong hệ thống, chỉ không hiện nữa. Báo bộ
phận kỹ thuật để khôi phục.

**Hạn hiển thị lệch giờ.**
Không nên xảy ra — toàn hệ thống dùng giờ Việt Nam ở mọi màn hình. Nếu thấy
lệch, báo ngay kèm ảnh chụp màn hình; đó là lỗi cần sửa, không phải cài đặt máy
bạn.

**Tôi nghỉ việc thì dữ liệu của tôi thế nào?**
Tài khoản bị vô hiệu hoá ngay, mọi phiên đăng nhập mất hiệu lực. Công việc,
bình luận và lịch sử làm việc **vẫn còn nguyên** — đó là hồ sơ công việc của cả
đội, không phải tài sản riêng của một tài khoản.

---

## 7. Báo lỗi thế nào cho nhanh được xử lý

Kèm đủ bốn thứ:

1. **Bạn đang làm gì** — "tôi bấm Dời hạn trên việc X".
2. **Bạn mong gì xảy ra**, và **thực tế xảy ra gì**.
3. **Ảnh chụp màn hình**, gồm cả thông báo lỗi nếu có.
4. **Thời điểm** xảy ra, và bạn dùng máy tính hay điện thoại.

Câu "hệ thống lỗi" không đủ để tìm ra nguyên nhân.
