# Chế độ trình diễn Chương 4

Điểm vào: `presentation.php`. Tài khoản quản trị cấp cao phải đăng nhập trước khi mở trang này.

Mode trình diễn tải các trang thật của CMC-eSA trong một khung toàn màn hình, nhưng sử dụng PHP session và SQLite riêng dưới `tmp/presentation/`. Dữ liệu nghiệp vụ và phiên đăng nhập gốc không bị thay đổi. Các đường dẫn được tính theo thư mục cài đặt nên dùng được khi website nằm trong thư mục con trên cPanel.

## Mạch trình diễn

1. Ở góc nhìn cán bộ, bắt đầu từ Tổng quan quản trị; con trỏ mở sidebar, chọn Chiến dịch và mở brief “Một ngày đi học ngành Digital Marketing”.
2. Màn chuyển vai báo rõ “Đại sứ sinh viên”, đưa người xem về Tổng quan sinh viên; con trỏ mở sidebar, chọn Chiến dịch nội dung, mở form, điền liên kết và chú thích bài nộp.
3. Màn chuyển vai báo rõ “Học sinh THPT”, sau đó mở widget trên website trường; học sinh chọn Content và mở bài đã duyệt.
4. Giữ nguyên widget và bài đang mở; con trỏ bấm Quay lại, chuyển sang Đại sứ, lọc ngành Digital Marketing và mở hồ sơ Trần Minh Anh.
5. Giữ nguyên hồ sơ đang xem; con trỏ bấm Gửi tin nhắn, nhập câu hỏi vào ô chat, bấm gửi và chờ phản hồi trước khi hỏi về học bổng.
6. Màn chuyển vai đưa người xem về Tổng quan sinh viên; đại sứ mở sidebar, vào Hộp thư tư vấn, chọn Mai Thu rồi chuyển câu hỏi học bổng đến Ban Tuyển sinh.
7. Màn chuyển vai đưa người xem về Tổng quan quản trị; cán bộ mở sidebar, chọn Kiểm duyệt chat, chọn vụ việc của Mai Thu và nhập xác nhận chính thức.
8. Giữ nguyên màn kiểm duyệt; con trỏ mở sidebar, quay về Tổng quan và nhấn vào luồng Chiến dịch để khép vòng: phản hồi từ Inbound quay lại định hướng nội dung Outbound.

Các cảnh 3-5 và 7-8 tiếp tục trên cùng trạng thái giao diện. Mode không tải lại trang giữa những bước thuộc cùng một vai trò.

## Điều khiển

- `→`, `Page Down` hoặc phím cách: cảnh tiếp theo.
- `←` hoặc `Page Up`: quay lại.
- `F`: toàn màn hình.
- `H`: ẩn hoặc hiện nhãn cảnh và thanh điều khiển.

Hiệu ứng gõ chữ và con trỏ dẫn sẵn mạch trình bày. Nếu người thuyết trình bấm chuột hoặc nhập liệu trong lúc hiệu ứng đang chạy, phần tự động dừng và nhường quyền điều khiển ngay. Sidebar, liên kết, modal, form và API tiếp tục hoạt động như website chính nhưng toàn bộ thay đổi chỉ được ghi vào database của phiên demo. Bấm `Tiếp theo` để trở lại kịch bản tự động ở cảnh kế tiếp.

## Đưa lên cPanel

- Chỉ có điểm vào mới `presentation.php`; menu và các luồng đang dùng của website không đổi.
- Phiên trình diễn chỉ mở sau khi đăng nhập tài khoản super admin. Iframe yêu cầu token ký, hết hạn sau 4 giờ.
- Mỗi lần mở tạo một SQLite riêng trong `tmp/presentation/`; các tệp demo quá 24 giờ được tự dọn. `.htaccess` chặn truy cập trực tiếp thư mục `tmp`.
- Hosting cần bật `PDO SQLite` và cho tiến trình PHP quyền ghi vào thư mục `tmp`. Nếu website nằm trong thư mục con, đường dẫn vẫn được tính theo vị trí cài đặt.
- Có thể đặt biến môi trường `PRESENTATION_SECRET` trên hosting. Nếu không đặt, hệ thống tự tạo khóa ký riêng trong thư mục demo.
