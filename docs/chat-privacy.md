# Quyền kiểm duyệt chat

- Admin thông thường không được mở màn hình kiểm duyệt hoặc gọi API đọc hội thoại của người khác.
- Super admin đang cấu hình trong `SUPER_ADMIN_EMAILS` phụ trách kiểm duyệt. Có thể chỉ định thêm tài khoản admin đang hoạt động qua `CHAT_MODERATOR_EMAILS`, danh sách email cách nhau bằng dấu phẩy. Không tự tạo role hay cấp quyền cho toàn bộ admin.
- Danh sách chỉ có hội thoại chứa tin đang bị gắn cờ hoặc câu hỏi chuyển tiếp đang chờ. Mở ID bất kỳ không trả lại nội dung.
- Hiển thị tối đa 5 tin bị gắn cờ gần nhất, mỗi tin kèm tối đa 2 tin trước và 2 tin sau. Không có chức năng mở rộng hay đọc toàn bộ lịch sử.
- Người kiểm duyệt được khôi phục tin bị gắn cờ, không được đánh dấu các tin ngữ cảnh để mở rộng dần quyền truy cập. Tin được khôi phục không còn cấp quyền đọc ngữ cảnh ở lần tải tiếp theo; xử lý lần lượt sẽ đưa những tin bị gắn cờ cũ hơn vào danh sách.
- Chuyển tiếp chỉ cho phép đọc câu hỏi đại sứ gửi, không cho đọc tin nhắn gốc. Quyền trả lời cũng thuộc người phụ trách.
- Ghi các lần mở vụ việc vào `program_audit`, entity `chat_privacy`, kèm actor, conversation và các ID tin được mở; không sao chép nội dung tin vào nhật ký. Chưa có giao diện tra cứu nhật ký riêng; quản trị kỹ thuật kiểm tra trong cơ sở dữ liệu.
- Widget và Inbox đại sứ thông báo phạm vi kiểm tra. Đây không phải mã hóa đầu-cuối: người quản trị hạ tầng/database vẫn có khả năng kỹ thuật truy cập dữ liệu; quyền ứng dụng không ngăn được quyền hạ tầng.
- Chưa có cơ chế báo cáo trực tiếp từng tin của học sinh, quyền đọc mở rộng theo phê duyệt, hoặc chính sách tự xóa dữ liệu theo thời hạn. Không hiển thị hay hứa các tính năng đó.

Kiểm thử: `tests/chat-privacy.php` kiểm tra phân quyền, ngữ cảnh giới hạn, thu hồi sau khôi phục và nhật ký không chứa nội dung; `tests/api-integrity.php` kiểm tra API từ chối admin đọc chat riêng; `tests/moderation-layout.php` kiểm tra render với fixture (không thay thế kiểm thử quyền).
