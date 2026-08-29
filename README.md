# eAmbassador

eAmbassador là nền tảng vận hành nội bộ cho hệ sinh thái đại sứ sinh viên số và truyền thông ngang hàng của Trường Đại học CMC. Khi mở hệ thống, người dùng đi thẳng vào cổng đăng nhập được phân quyền cho Quản trị viên, Sinh viên và Đại sứ.

## Công nghệ

- Backend: PHP 8.1+ thuần, PDO và SQLite
- Frontend: HTML5, Bootstrap 5.3, Bootstrap Icons, CSS riêng và JavaScript thuần
- Không dùng Node.js, Python hay Tailwind trong source/runtime của website

## Hệ thống giao diện

- Nhận diện CMC University: primary `#008FD5`, navy `#002757`, cyan `#00DEDF`
- Logo ngang CMC University dùng tại login; logo vector SVG dùng trong app shell
- Design system dùng chung nằm ở `assets/css/app.css`, theo hướng Campus Wayfinding với một nguồn token duy nhất cho toàn bộ public, student và admin
- Visual hero gốc nằm ở `assets/img/cmc-connect-hero.png`; ảnh Humans of CMCU nằm cùng thư mục
- Có breakpoint mobile/tablet/desktop, sidebar off-canvas có quản lý focus, chuyển động trạng thái nhẹ và chế độ giảm chuyển động theo thiết bị

## Chạy dự án

Yêu cầu PHP có extension `pdo_sqlite` và `mbstring`.

```bash
php -S 127.0.0.1:8000 router.php
```

Mở `http://127.0.0.1:8000`. Database và dữ liệu mẫu được tự động tạo ở `data/p2p_cmc.sqlite` trong lần chạy đầu tiên.

### Kiểm duyệt tin nhắn

Chat được kiểm duyệt phía server trước khi hiển thị cho người nhận. Có thể dùng bất kỳ nhà cung cấp AI nào hỗ trợ API Chat Completions tương thích OpenAI bằng cách truyền URL endpoint, API key và model khi chạy ứng dụng:

```bash
AI_MODERATION_API_URL="https://provider.example/v1/chat/completions" \
AI_MODERATION_API_KEY="your-key" \
AI_MODERATION_MODEL="provider-model-name" \
php -S 127.0.0.1:8000 router.php
```

Rule kiểm duyệt nằm trong ứng dụng và yêu cầu model trả về JSON gồm quyết định, nhóm vi phạm, độ tin cậy và lý do. Ngưỡng chặn mặc định là `0.65`, có thể chỉnh bằng `AI_MODERATION_THRESHOLD`. Khi thiếu cấu hình hoặc API tạm thời không khả dụng, bộ lọc tiếng Việt cục bộ sẽ tiếp quản. Không đưa API key vào JavaScript hoặc commit key vào repository.

## Tài khoản demo

Tất cả tài khoản dùng mật khẩu `123456`.

| Vai trò | Email |
|---|---|
| Quản trị viên | `admin@cmc.edu.vn` |
| Sinh viên | `student@cmc.edu.vn` |
| Đại sứ sinh viên | `ambassador@cmc.edu.vn` |

## Chức năng chính

- Admin: quản lý sinh viên, đại sứ, chiến dịch/brief, kho UGC, chỉ số views/likes/tương tác, tính bonus, phân hạng Junior/Senior, chính sách, kiểm duyệt hội thoại và lịch tư vấn từ widget website.
- Sinh viên: nhận nhiệm vụ, dùng AI Copilot tạo ba hướng kịch bản và kiểm tra brand voice, nộp TikTok/Reels/Shorts, theo dõi hiệu quả nội dung, bảng xếp hạng và ví điểm.
- Đại sứ: toàn bộ chức năng sinh viên, cộng thêm inbox P2P, quality score hội thoại, trạng thái hỗ trợ và trình soạn blog đưa trải nghiệm đã duyệt lên widget.
- Học sinh THPT: dùng widget nhúng trên website chính thức để lọc đại sứ theo ngành, quê quán, khóa và trạng thái; đọc Content đã duyệt; chat với người đang online hoặc đặt lịch với người đang offline.
- Hiệu quả UGC: tập trung lượt xem, lượt thích, bình luận và chia sẻ theo bài đăng, chiến dịch và nền tảng.

## Cấu trúc

```text
app/                 Khởi tạo ứng dụng, database, helper và phân quyền
assets/              CSS và JavaScript giao diện
includes/            Layout, thanh điều hướng và sidebar
pages/admin/         Màn hình quản trị
pages/student/       Màn hình sinh viên và đại sứ
pages/public/        Landing page, danh bạ đại sứ và đăng nhập
actions.php          Xử lý form nghiệp vụ
api.php              API JSON cho chat widget, lịch tư vấn, inbox và AI Copilot
index.php            Front controller
router.php           Router cho PHP development server
```

## Ghi chú triển khai thật

MVP dùng SQLite để có thể trình diễn ngay. Khi triển khai production, nên đổi DSN sang MySQL/PostgreSQL, đặt web root tại thư mục riêng, bật HTTPS, dùng SMTP/SSO của trường, thêm rate limiting cho chat và lưu file UGC qua object storage.
