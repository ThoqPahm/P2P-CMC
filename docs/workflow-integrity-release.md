# Liên kết luồng dữ liệu — đợt sửa 06/09/2026

## Đã triển khai

- Guest chat dùng danh tính riêng và token từng hội thoại; email chưa xác minh không được dùng để nhận lại lịch sử hoặc đăng nhập tài khoản có sẵn. Lịch sử widget tiếp tục lưu trên trình duyệt. Email chỉ là thông tin liên hệ.
- Phiên đăng nhập kiểm tra tài khoản còn active ở mỗi request. Online dựa trên hoạt động gần nhất (hai phút), không phải cờ seed. Đây chưa phải lịch trực hoặc cam kết phản hồi.
- Danh sách công khai, widget, API và AI dùng chung điều kiện nhận tư vấn: active, policy approved, không vi phạm red; người đã có hồ sơ chương trình còn phải được duyệt, đang tham gia và hoàn thành ba mô-đun. Đại sứ cũ chưa có hồ sơ được giữ quyền hiện tại, không tự nâng vai trò sinh viên.
- Chiến dịch quá hạn không nhận bài hoặc yêu cầu copilot mới. Ngày cuối vẫn nhận bài theo múi giờ Việt Nam. Admin có xem brief và đóng/mở chiến dịch trong hạn.
- Duyệt bài mới và ví cùng transaction; duyệt lặp không tăng điểm. Duyệt lại chỉ ghi chênh lệch; từ chối thu hồi điểm đã cấp bởi luồng mới. Trang duyệt giữ chỉ số cũ trong form và hiển thị số điểm thực ghi nhận.
- Cập nhật số liệu hiệu quả chỉ thay đổi báo cáo, không tự đổi ví. Muốn tính thưởng lại cần duyệt lại rõ ràng.
- Điểm lịch sử được giữ nguyên, đánh dấu cần đối soát và khóa duyệt lại để tránh điều chỉnh ngoài ý muốn. Admin xem danh sách tại Quản lý thưởng.
- Lịch hẹn liên kết hội thoại khi có token chứng minh; đại sứ thấy yêu cầu trong Inbox, học sinh xem kết quả xác nhận ở Inbox widget trên trình duyệt đã đặt lịch. Không hứa tự gửi email.
- Có thể chuyển tuyến câu hỏi tiếp theo sau khi đã trả lời; lưu dấu vết câu trước. Trả lời chính thức và cập nhật hội thoại cùng transaction, tránh ghi trùng khi gửi lặp.
- Tin bị gắn cờ không xuất hiện trong preview Inbox. Đánh giá người dùng và chỉ số kiểm duyệt không còn ghi đè nhau.
- Báo cáo không dùng điểm đánh giá giả khi thiếu mẫu; tổng views không bị nhân do JOIN. Nguồn AI hiển thị số thực đủ điều kiện, sửa nội dung yêu cầu xác nhận lại.
- Migration không ghi đè hồ sơ/nội dung nguồn đang vận hành; không đổi loại giao dịch ví cũ. Nút đổi thưởng chưa có nghiệp vụ được vô hiệu hóa rõ ràng.

## Còn cần hoàn thiện hoặc quyết định vận hành

- Chưa có SMTP/outbox và tác vụ gửi email; hiện theo dõi qua Inbox. Không kích hoạt gửi thư tới người thật trong kiểm thử.
- Chưa triển khai quy đổi/quy trình duyệt đổi quà. Cần chốt loại quà, tồn kho và chính sách trừ/hoàn điểm.
- Chưa tự đối soát điểm lịch sử. Cần xác nhận cách xử lý từng giao dịch trước khi điều chỉnh số dư.
- Nguồn tuyển sinh vẫn cần người có thẩm quyền xác nhận trong quản trị nguồn; không tự duyệt thay trường.
- Nhiệm vụ chương trình hiện lưu kết quả riêng, chưa có khóa ngoại tới bài UGC/hội thoại. Hoàn thành nhiệm vụ không đồng nghĩa duyệt UGC hoặc tự cộng thưởng.
- Database trống vẫn khởi tạo demo theo cơ chế hiện tại. Không coi bản cài mới này là dữ liệu vận hành thật; cần tách seed khi triển khai production mới.
- Chưa chứng nhận toàn bộ UI trên mọi thiết bị, email, nhà cung cấp AI thực hoặc hosting cPanel.

## Kiểm thử

- `php tests/workflow-integrity.php`: 21 kiểm tra SQLite riêng trong RAM.
- `php tests/ambassador-program.php`: 25 kiểm tra chương trình/quyền/governance.
- `php tests/moderation-layout.php`: 13 kiểm tra render kiểm duyệt.
- `php tests/widget-admin-layout.php`: 15 kiểm tra render quản trị widget.
- `php tests/api-integrity.php http://127.0.0.1:8002`: 32 kiểm tra trên router QA với SQLite tách biệt, gồm token, danh tính guest, lịch xác nhận, các route theo vai trò.
- Kiểm tra cú pháp PHP các file thay đổi, JavaScript widget và `git diff --check`.

Trước cập nhật cPanel: sao lưu nhất quán database SQLite bằng cơ chế backup SQLite, cấu hình và các file upload. Migration chạy khi bootstrap; không xóa database, không đưa khóa API hoặc dữ liệu người dùng lên Git. Sau cập nhật cần smoke test lại trên hosting thật. Bộ test API chỉ dành cho router QA, không chạy trên web vận hành.
