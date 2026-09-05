# URL điều hướng

Alias được khai báo duy nhất trong `app/Routes.php`, không đổi tên file trang hoặc API.

| Trước | Sau |
|---|---|
| `index.php?page=login` | `/dang-nhap` |
| `index.php?page=admin-campaigns` | `/admin/chien-dich` |
| `index.php?page=admin-widget` | `/admin/widget` |
| `index.php?page=super-admin` | `/admin/ky-thuat` |
| `index.php?page=inbox&conversation=12` | `/hop-thu/12` |
| `index.php?page=ambassador-program&tab=knowledge` | `/chuong-trinh-dai-su/nguon-thong-tin` |
| `index.php?page=widget` | `/widget` |

URL cũ GET/HEAD chuyển 302 sang alias, giữ bộ lọc. Dùng 302 để có thể đổi alias mà không mắc cache chuyển hướng vĩnh viễn. Phân quyền giữ nguyên: URL đẹp không thay thế đăng nhập hay kiểm tra quyền.

Link HTML hiện hữu được chuyển tại ranh giới render qua `Routes::html`; redirect dùng chung `Routes::legacy`. Code mới nên dùng `Routes::url($page, $params)` trực tiếp. Không sửa chuỗi nội dung, endpoint API hay POST bằng chuyển hướng. Base URL giúp asset, form và fetch tương đối hoạt động tại đường dẫn nhiều cấp; liên kết fragment được giữ tại tài liệu hiện tại.

## Triển khai

- cPanel/Apache: đưa cả `.htaccess` (file ẩn) lên cùng `index.php`. Cần Apache 2.4 với mod_rewrite và quyền override cấu hình rewrite/Options. Hoạt động ở domain root hoặc thư mục con, không cần hard-code RewriteBase.
- Không xóa/ghi đè cấu hình PHP do cPanel quản lý nếu máy chủ đang có `.htaccess` riêng; ghép khối rewrite của repo vào trước các quy tắc catch-all hiện có.
- Local: `php -S 127.0.0.1:8000 router.php` tại thư mục dự án.
- Nginx không đọc `.htaccess`; quản trị hosting cần cấu hình front controller tương ứng trước khi dùng URL mới.
- Sau pull, kiểm tra `/dang-nhap`, trang admin, `/widget`, tải CSS/JS, gửi form và URL cũ. Chưa kiểm thử trên hosting cPanel thật.

Query string cho bộ lọc, phiên API hoặc cache asset vẫn được giữ đúng chức năng. Đây không phải thông tin nên ép toàn bộ thành slug. Widget nhúng cũ vẫn dùng được thông qua redirect; mã nhúng mới dùng `/widget`.

Kiểm thử: `php tests/routes.php`, `php tests/widget-admin-layout.php`, và suite API với router/database QA tách biệt.
