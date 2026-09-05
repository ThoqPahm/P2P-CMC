# Rà soát tính nhất quán hệ thống, 06/09/2026

## Phạm vi và giới hạn

Kiểm tra mã nguồn local hiện tại, bao gồm các thay đổi chưa commit; không đồng nhất với bản đang chạy trên cPanel/GitHub. Đã đọc các route, handlers, schema, các luồng đại sứ/UGC/chat/lịch/AI/báo cáo; chạy test có sẵn và truy vấn tổng hợp trên SQLite ở chế độ read-only. Không gọi AI trả phí, không gửi email, không thay đổi dữ liệu thực. Đây chưa phải kiểm thử end-to-end mọi tổ hợp trạng thái hay xác nhận an toàn production.

## Kết quả dữ liệu local

- 2 nhóm `(user_id, reference_id)` có nhiều giao dịch credit cùng reference_type=submission. Chưa phân loại từng giao dịch là thưởng bổ sung hợp lệ hay cộng lặp.
- 1 bài chưa approved nhưng có credit tham chiếu. Seed hiện tại cũng tạo bài pending đi kèm credit, nên không thể quy toàn bộ sai lệch cho thao tác duyệt.
- 8 mục kiến thức active; 0 bản governance approved; 0 nguồn đủ điều kiện cho chatbot.
- 0 chiến dịch active quá hạn ở thời điểm kiểm tra. Lỗi thiếu chặn quá hạn vẫn tồn tại trong code.
- 0 lỗi khóa ngoại qua `pragma_foreign_key_check`. Khóa ngoại đúng không chứng minh nghiệp vụ đúng.
- Test có sẵn: chương trình đại sứ 25, moderation layout 13, widget-admin layout 15 kiểm tra đạt; icon coverage đạt. Các test này chưa bao phủ các điểm đứt gãy bên dưới.

## Phát hiện ưu tiên

### P0. Nhận diện học sinh qua email không có xác minh

`api.php:156–199` (widget_start_chat): tìm prospect theo email, lấy hội thoại open hiện có rồi thay public_token và trả token mới. Không có OTP, magic link hay kiểm tra token cũ trước khi cấp quyền hội thoại. Người nhập email của người khác có thể nhận quyền hội thoại của email đó. Việc xoay token còn làm phiên trên trình duyệt cũ mất quyền.

`api.php:257–300` (start_chat) cũng gán session user_id cho prospect tìm bằng email, không chứng minh quyền sở hữu. Cần sửa cả hai đường vào, không chỉ frontend widget. Chưa khai thác trên dữ liệu người dùng thật.

### P1. Duyệt bài và ví điểm không có cơ chế chống cộng lặp/đảo thưởng

`actions.php:227–252`: cộng credit mỗi khi trạng thái đổi từ không-approved sang approved. Không có ràng buộc unique cho thưởng cơ sở; không có debit/đảo thưởng khi rút duyệt. Chuỗi approved → rejected → approved có thể cộng thêm lần nữa. Chỉnh chỉ số của bài approved cũng không đối soát số điểm đã ghi vào ví.

`actions.php:255–271` cập nhật views/likes/comments/shares nhưng không tính lại bonus_points/ledger. Cần thống nhất thưởng cố định lúc duyệt hay điều chỉnh theo hiệu quả về sau, dùng giao dịch có reference và chống xử lý lặp. Không tự xóa điểm cũ trước khi phân loại giao dịch và chốt chính sách.

### P1. Nguồn AI được bật nhưng chưa đủ điều kiện sử dụng

`actions.php:158–181` quản lý nội dung và is_active; `app/WidgetChatAssistant.php:40` chỉ lấy `AmbassadorProgram::knowledge(..., true)`. Hàm này còn yêu cầu governance approved, official, chưa hết hạn và hash nội dung khớp. Local đang có 8 active nhưng 0 usable. Cơ chế chặn nguồn chưa xác nhận là đúng; phần quản trị chưa biểu đạt đủ trạng thái để người vận hành biết bước tiếp theo. Chỉnh nội dung làm xác nhận cũ mất hiệu lực, cần hiển thị “cần xác nhận lại”, không chỉ “đã cập nhật”.

### P1. Hồ sơ chương trình và vai trò đại sứ là các hệ trạng thái độc lập

`app/AmbassadorProgram.php` review_application, complete_training và review_member chỉ cập nhật bảng chương trình; `actions.php:273–294` cập nhật role/status/policy riêng. `pages/public/widget.php:6`, `api.php:114–120,156–159` và gợi ý AI chỉ xét role=ambassador, status=active. Chưa xét participation paused, policy suspended hay hoàn thành đào tạo.

Do đó “được tiếp nhận”, “đã đào tạo”, “đang tham gia”, “được phép trực chat” chưa có quy tắc nối thống nhất. Cần chốt một hàm điều kiện nhận tư vấn và dùng chung ở danh sách, API, AI, báo cáo; không tự động nâng vai trò chỉ vì duyệt hồ sơ nếu chưa chốt nghiệp vụ.

### P1. Tạm khóa không thu hồi đầy đủ quyền phiên đã đăng nhập

`actions.php:24–35` kiểm tra status khi login, nhưng `app/helpers.php:10–37` đọc user và require_auth không chặn inactive. Các API đọc user trực tiếp cũng không đồng nhất với `AmbassadorProgram::handle`, vốn có chặn active. Khóa tài khoản sau khi đã đăng nhập có thể không chặn các thao tác ở module cũ.

### P1. Thông báo email và lịch hẹn chưa có luồng thực thi đầy đủ

`api.php:244–255` lưu lịch rồi thông báo sẽ xác nhận qua email; `actions.php:305–312` chỉ đổi status. `assets/js/widget.js:387,426,607` hứa báo email khi có phản hồi. Không tìm thấy mail transport/outbox/job gửi email trong mã ứng dụng đã rà. Có thể cần đội ngũ xử lý email thủ công, nhưng giao diện hiện trình bày như tự động.

Schema consultation_appointments chỉ tham chiếu ambassador, chưa liên kết prospect/conversation. Chưa thấy lịch được đưa vào inbox đại sứ hay API cho học sinh xem kết quả xác nhận. Cần nối yêu cầu → người phụ trách → xác nhận → thông báo → hoàn thành/hủy, có nhật ký gửi thất bại và chống gửi trùng.

### P1. Báo cáo có số mặc định và công thức không nhất quán

`pages/admin/performance.php:41–46`: active knowledge bị tính là verified; khi chưa có rating dùng 4.8, 4.9 và quality 82. Các chỗ tỷ lệ 100% chưa có mẫu số thực cũng dễ gây hiểu sai. Trong khi `AmbassadorProgram::metrics` trả null khi thiếu đánh giá và đếm nguồn usable đúng điều kiện.

`pages/admin/ambassadors.php:4`: JOIN đồng thời conversations và submissions trước SUM(views), làm nhân lượt xem khi một đại sứ có nhiều hội thoại. Cần aggregate từng nguồn trước khi JOIN. Đây là lỗi công thức, không phải dữ liệu gốc bị nhân.

### P2. Một chỉ số chất lượng bị nhiều luồng ghi đè

`api.php:77–83` tính quality từ số tin và số flag; `api.php:227–241` cộng/trừ trực tiếp quality mỗi lần gửi đánh giá. Gửi rating lặp có thể làm điểm dịch chuyển; tin nhắn tiếp theo lại tính lại theo công thức khác. `actions.php:314–320` đổi flag không cập nhật quality. Cần tách đánh giá người dùng, an toàn nội dung, tốc độ phản hồi và tính tổng hợp theo một công thức duy nhất.

### P2. Chiến dịch thiếu kiểm soát vòng đời

`actions.php:38–49` chỉ kiểm tra deadline không rỗng; submit_content/submit_blog chỉ kiểm tra active, không kiểm tra hạn nộp. `pages/student/campaigns.php` và dashboard cũng lọc active. Chiến dịch quá hạn có thể vẫn nhận bài. Trang admin có nút Xem chi tiết chưa gắn hành động và chưa có handler sửa/đóng chiến dịch. Cần chốt hạn nộp theo timezone, cách đóng/mở lại và chính sách nộp nhiều phiên bản.

### P2. Nhiệm vụ chương trình và UGC chưa nối đầu ra

`ambassador_tasks` có kind=content/consultation/event nhưng không có liên kết campaign/submission/conversation/appointment. Hoàn thành review_task không phản ánh thành bài UGC hoặc thưởng. Có thể đây là các nghiệp vụ khác nhau, nhưng cần thể hiện rõ và cho liên kết kết quả thay vì bắt người dùng nhập trùng.

### P2. Ví điểm có nút quy đổi nhưng chưa có nghiệp vụ quy đổi

`pages/student/wallet.php:9` hiển thị các nút Đổi và hứa xác nhận bởi phòng CTSV. Chưa có form/handler/JS xử lý redeem, trừ điểm, yêu cầu đổi hay duyệt thưởng. Cần triển khai đầy đủ hoặc ghi rõ tính năng chưa khả dụng, không để nút có vẻ hoạt động.

### P2. Online/offline là cờ dữ liệu, chưa phải trạng thái hiện diện thực

`users.is_online` được seed và dùng ở widget/AI/thống kê; chưa tìm thấy luồng heartbeat, last_seen hay cập nhật cờ khi đăng nhập/đăng xuất. Có thể hiển thị online khi người dùng đã rời hệ thống. Cần phân biệt “đang hoạt động”, “sẵn sàng nhận chat” và “đang trong ca trực”.

### P2. Chuyển tuyến chưa theo cùng quy tắc cập nhật hội thoại

`actions.php:335–353` cập nhật xác nhận và INSERT message riêng lẻ, không transaction, không refresh last_message_at như gửi chat thường. `pages/student/inbox.php` chỉ cho chuyển khi is_escalated rỗng; sau answered cờ vẫn là 1 nên không có vòng chuyển tuyến câu hỏi mới trong cùng chat. Cần từng yêu cầu chuyển tuyến có ID/trạng thái riêng hoặc quy tắc mở lại rõ.

### P2. Dữ liệu mẫu trộn với dữ liệu vận hành

`app/Database.php:390–395` seed bài pending cùng credit. Migration còn tự bổ sung blog đã duyệt gắn ID user/campaign cố định. Cần tách seeding demo khỏi migration production và đánh dấu nguồn dữ liệu để báo cáo không coi nội dung mẫu là hoạt động thực.

## Thứ tự xử lý đề xuất

1. Chặn nhận quyền chat qua email chưa xác minh; kiểm tra khóa tài khoản; xác định quyền thống nhất.
2. Chốt nguồn dữ liệu chuẩn và sơ đồ chuyển trạng thái cho đại sứ, chiến dịch, bài nộp, điểm, chat và lịch.
3. Sửa ledger điểm với chống xử lý lặp, lập báo cáo đối soát dữ liệu cũ trước mọi điều chỉnh.
4. Nối governance AI vào màn hình cấu hình; thống nhất báo cáo và loại số mặc định/mẫu.
5. Hoàn thiện lịch/email, chuyển tuyến, quy đổi và presence theo chính sách đã chốt.
6. Kiểm thử end-to-end theo từng vai trò, cả trường hợp từ chối/quá hạn/gửi lặp/tạm khóa; kiểm thử riêng local, staging và cPanel.

Chưa sửa mã chức năng hoặc dữ liệu trong đợt rà soát này. Cần được chấp thuận triển khai và chốt chính sách trước các thay đổi có ảnh hưởng quyền, điểm thưởng và thông báo gửi ra ngoài.
