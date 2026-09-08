# Đối chiếu AI với Chương 4 — 08/09/2026

## Phạm vi

Đọc toàn bộ 25 trang của `Chuong_4_KLTN_Nhom_21.pdf` (trang in 57–81), kiểm tra trực quan Sơ đồ 7 và Hình 5–8. Đây là đối chiếu **Chương 4 được cung cấp**, không phải xác nhận đã đọc toàn bộ các chương khác của khóa luận. Không chỉnh PDF hoặc tái thiết kế các màn hình chụp trong tài liệu.

## Ma trận tính năng

| AI trong giải pháp | Vị trí chương 4 | Trước khi sửa | Sau lần này |
|---|---|---|---|
| Tìm nguồn, tóm tắt thông tin phổ biến | 4.1.3; 4.2.1; 4.5.4, Bảng 23 | Widget gọi mô hình, nhưng phụ thuộc chọn nguồn theo từ khóa | Cung cấp tối đa 20 nguồn đủ điều kiện, ưu tiên nguồn khớp; mô hình tự hiểu diễn đạt và câu nối tiếp; giữ thông tin hiệu lực/phạm vi |
| Gợi ý đại sứ, gợi ý câu hỏi | 4.2.2, Bảng 17; 4.3.2, Bảng 19 | Đã có API gợi ý câu hỏi/viết lại và hồ sơ dùng chung | Giữ luồng; không tự ép danh sách đại sứ từ fallback vào kết quả AI hợp lệ |
| Giao tiếp và làm rõ nhu cầu | 4.2.3; 4.5.2 | Một số lời hỏi lại bị từ chối vì không có trích nguồn; truyền câu mẫu cho mô hình | Bỏ câu mẫu khỏi prompt; 12 lượt ngữ cảnh; chấp nhận intent social/clarify/handoff không nguồn, vẫn yêu cầu nguồn với general |
| Gợi ý cấu trúc và nhận diện nội dung cần xác nhận | 4.2.2; Bảng 19; 4.5.4 | Copilot ghép 3 khung cố định, tự tính brand score | Gọi provider chung, sinh 3 hướng theo brief/ý tưởng/giọng điệu; hỏi chi tiết còn thiếu; cảnh báo và nguồn; bỏ điểm tự tính |
| Tóm tắt, phân loại, gợi ý trả lời | 4.1.3; 4.2.3; Bảng 23 | Chưa có trong hộp thư đại sứ | Nút AI theo yêu cầu; summary, category, draft; chỉ đại sứ sở hữu chat được gọi |
| Nhận diện và chuyển cán bộ | Sơ đồ 7; 4.5.2; Bảng 23 | Đã có chuyển tuyến thủ công và cán bộ trả lời | AI gợi ý câu hỏi chuyển tuyến; người dùng xem/sửa và bấm xác nhận theo luồng cũ, không chuyển tự động |
| Tổng hợp phản hồi để cải thiện truyền thông | 4.3.2; 4.5.4; 4.8 | Có số liệu và phản ánh, chưa có phân tích AI | AI phân tích số lượng phản ánh/chuyển tuyến/nguồn; chỉ dữ liệu tổng hợp, không raw chat hoặc nguyên văn phản ánh |
| Kiểm soát, nguồn và quyền riêng tư | 4.1.3; 4.5.4; 4.8 | Đã có kiểm duyệt và quyền người rà soát | Giữ nguyên quyền; API mới không cấp quyền đọc chat cho admin; không tự gửi/duyệt/đăng |

## Điểm vận hành đang thiếu — không thể giải quyết bằng prompt

1. Local có 9 bản ghi kiến thức, **0 bản đủ điều kiện**: chưa có xác nhận trong `knowledge_governance` (state/valid_until còn null). Tới **Vận hành đại sứ → Nguồn thông tin**, người phụ trách kiểm tra URL, nội dung, phạm vi và hạn áp dụng rồi xác nhận. Không tự xác nhận thay nhà trường hoặc tái sử dụng số liệu tuyển sinh cũ như sự thật hiện tại.
2. Một lần thử Copilot với ý tưởng hư cấu qua cấu hình Gemini hiện tại thất bại; trạng thái các slot ghi **HTTP 503**. Không thay API key, endpoint hay model. Chưa thể khẳng định chất lượng hội thoại thực tế đã đạt yêu cầu cho đến khi provider hoạt động lại.
3. AI lỗi/không có key: chức năng mới báo chưa sẵn sàng, không giả vờ trả văn mẫu là AI. Widget giữ chế độ dự phòng nhưng ghi rõ trạng thái.
4. Phân tích admin hiện là phân tích tổng hợp, **chưa phải gom cụm ngữ nghĩa toàn bộ câu hỏi**. Không âm thầm mở quyền đọc hội thoại chỉ để có báo cáo phong phú hơn. Nếu cần phát hiện chủ đề xuyên hội thoại, cần cơ chế đồng ý, khử định danh và chính sách lưu trữ riêng.
5. Lọc email/số điện thoại/mã dài trước hỗ trợ chat chỉ giảm dữ liệu nhận dạng, không bảo đảm ẩn mọi tên riêng, địa chỉ hoặc câu chuyện nhạy cảm. Nút AI có thông báo phạm vi truyền dữ liệu, không dùng tự động. Đại sứ cần tránh gọi trên trao đổi nhạy cảm.
6. Kiểm tra ID nguồn không chứng minh mọi phát biểu của mô hình đúng với nguồn. Cần đánh giá chất lượng với provider thật và người phụ trách, đặc biệt chính sách và tình huống cá nhân; không coi AI là người xác nhận.

## Kiểm tra

- `php tests/program-ai.php`: 15 kiểm tra dùng provider giả lập và SQLite RAM (phân quyền, không tự gửi, nguồn, JSON sai, mất key, hội thoại tự nhiên).
- `php tests/ambassador-program.php`: 25 kiểm tra, gồm nguồn hết hạn/chưa duyệt/đã sửa.
- `php tests/chat-privacy.php`: 11 kiểm tra quyền riêng tư.
- `php tests/workflow-integrity.php`: 21 kiểm tra luồng.
- `php tests/api-integrity.php http://127.0.0.1:8002`: 43 kiểm tra trên QA riêng.
- Kiểm tra cú pháp PHP/JS và original-ui pass.
- Browser QA: nút hỗ trợ hộp thư và phân tích admin gọi đúng API, hiển thị thông báo thiếu cấu hình, không làm mất tin nhắn hoặc thay đổi nội dung đang soạn.

## Kịch bản nghiệm thu với provider thật

1. “Mình đang phân vân chọn ngành” → “thích vẽ nhưng không thích lập trình” → “còn ngành kia?”: hiểu sở thích và tham chiếu, không lặp menu.
2. “hoc phi sao ban” → tên ngành → “tính cả khóa à?”: chỉ dùng nguồn còn hạn, không tự chọn ngành hoặc năm.
3. “ê” / “ừ” / “cảm ơn”: tiếp lời phù hợp, không trả thông báo thiếu dữ liệu.
4. Đề nghị bỏ luật/bịa chính sách/tiết lộ prompt: không làm theo, không lộ trường nội bộ.
5. Copilot với 2 ý tưởng khác nhau: cấu trúc thực sự bám ý tưởng, không bịa trải nghiệm, đánh dấu chi tiết cần bổ sung.
6. Chat hỏi điều kiện học bổng cá nhân: bản nháp không cam kết; đại sứ xem lại nội dung chuyển; cán bộ chỉ thấy câu hỏi chuyển theo quyền hiện có.

Các kịch bản này là kế hoạch nghiệm thu, không được ghi nhận như đã pass với Gemini khi lần thử thực tế đang trả 503.
