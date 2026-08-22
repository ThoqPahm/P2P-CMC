# CMC Connect

CMC Connect là nền tảng vận hành nội bộ cho hệ sinh thái đại sứ sinh viên số và truyền thông ngang hàng của Trường Đại học CMC. Khi mở hệ thống, người dùng đi thẳng vào cổng đăng nhập được phân quyền cho Quản trị viên, Sinh viên và Đại sứ.

## Công nghệ

- Backend: PHP 8.1+ thuần, PDO và SQLite
- Frontend: HTML5, Bootstrap 5.3, Bootstrap Icons, CSS riêng và JavaScript thuần
- Không dùng Node.js, Python hay Tailwind trong source/runtime của website

## Hệ thống giao diện

- Nhận diện CMC University: primary `#008FD5`, navy `#002757`, cyan `#00DEDF`
- Logo ngang CMC University dùng tại login; logo vector SVG dùng trong app shell
- Design system nằm ở `assets/css/cmc-brand.css`, xây trên Bootstrap 5 và CSS thuần
- Visual hero gốc nằm ở `assets/img/cmc-connect-hero.png`; ảnh Humans of CMCU nằm cùng thư mục
- Có breakpoint desktop/mobile, focus state, chuyển động nhẹ và chế độ giảm chuyển động theo thiết bị

## Chạy dự án

Yêu cầu PHP có extension `pdo_sqlite` và `mbstring`.

```bash
php -S 127.0.0.1:8000 router.php
```

Mở `http://127.0.0.1:8000`. Database và dữ liệu mẫu được tự động tạo ở `data/p2p_cmc.sqlite` trong lần chạy đầu tiên.

## Tài khoản demo

Tất cả tài khoản dùng mật khẩu `123456`.

| Vai trò | Email |
|---|---|
| Quản trị viên | `admin@cmc.edu.vn` |
| Sinh viên | `student@cmc.edu.vn` |
| Đại sứ sinh viên | `ambassador@cmc.edu.vn` |

## Chức năng chính

- Admin: analytics, quản lý chiến dịch/brief, duyệt UGC, tính bonus và hệ số thưởng, phân hạng Junior/Senior/Lead, theo dõi điều kiện/chính sách/vi phạm, leads và kiểm duyệt hội thoại.
- Sinh viên: nhận nhiệm vụ, dùng AI Copilot tạo ba hướng kịch bản và kiểm tra brand voice, nộp TikTok/Reels/Shorts, tạo link affiliate, theo dõi click/lead, leaderboard và ví điểm.
- Đại sứ: toàn bộ chức năng sinh viên, cộng thêm inbox P2P, quality score hội thoại và trạng thái chuyển CRM.
- Học sinh THPT: lọc hồ sơ theo ngành/quê quán/sở thích, đăng ký nhanh và trò chuyện trực tiếp với đại sứ.
- Affiliate: mỗi link có mã riêng; lượt click, form lead và thưởng điểm được ghi nhận tự động.

## Cấu trúc

```text
app/                 Khởi tạo ứng dụng, database, helper và phân quyền
assets/              CSS và JavaScript giao diện
includes/            Layout, thanh điều hướng và sidebar
pages/admin/         Màn hình quản trị
pages/student/       Màn hình sinh viên và đại sứ
pages/public/        Landing page, danh bạ đại sứ, affiliate và đăng nhập
actions.php          Xử lý form nghiệp vụ
api.php              API JSON cho chat thời gian gần thực và AI Copilot
index.php            Front controller
router.php           Router cho PHP development server
```

## Ghi chú triển khai thật

MVP dùng SQLite để có thể trình diễn ngay. Khi triển khai production, nên đổi DSN sang MySQL/PostgreSQL, đặt web root tại thư mục riêng, bật HTTPS, dùng SMTP/SSO của trường, thêm rate limiting cho chat và lưu file UGC qua object storage.
