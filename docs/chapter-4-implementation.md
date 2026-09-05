# Triển khai giải pháp Chương 4

## Phạm vi và giao diện giữ nguyên

Nguồn: `Chuong_4_KLTN_Nhom_21.pdf`, 25 trang, số trang in 57–81. Không chỉnh sửa PDF.

Bốn hình ở trang PDF 19–20 (trang in 75–76) được khóa về giao diện:

| Hình | Màn hình | Nguyên tắc |
| --- | --- | --- |
| 5 | Widget tìm đại sứ | Không thay HTML/CSS mặc định |
| 6 | Quản lý chiến dịch | Không sửa bố cục, sidebar, typography |
| 7 | Tổng quan quản trị | Không sửa bố cục, sidebar, typography |
| 8 | Kiểm duyệt hội thoại | Không sửa bố cục, sidebar, typography |

CSS mới không được nạp trên các route này. Những thay đổi chưa commit đã tồn tại trước đợt làm việc được giữ nguyên, không tự hoàn nguyên chúng về hình trong PDF. Không thay sidebar toàn cục để giữ các hình khóa luận.

## Chức năng mới

Điểm vào: `index.php?page=ambassador-program`. Liên kết nằm cuối các trang nội bộ không thuộc bốn màn hình khóa. Super Admin có thêm đường dẫn kiểm chứng ngay trong khu vực Dữ liệu gốc.

| Nội dung Chương 4 | Thực hiện |
| --- | --- |
| Vòng đời đại sứ, Bảng 22 | Hồ sơ tự nguyện, động lực, chủ đề, kỹ năng, thời gian; duyệt kèm phản hồi; ba bài định hướng có kiểm tra; công việc nội dung/tư vấn/sự kiện có người hướng dẫn và hạn; nộp, yêu cầu bổ sung, ghi nhận hoàn thành; phản hồi và đánh giá tiếp tục/tạm nghỉ/đổi vai trò |
| Nguồn tin và trách nhiệm | Tách nguồn chính thức/trải nghiệm; URL, phạm vi áp dụng, hạn rà soát, người xác nhận, lịch sử nội dung tại thời điểm duyệt |
| AI có kiểm soát | Chỉ dùng nguồn chính thức đã xác nhận, còn hạn, đang bật và nội dung khớp phiên bản được duyệt. Không dùng trải nghiệm cá nhân làm căn cứ chính sách |
| Cải thiện từ phản hồi | Thành viên báo nội dung thiếu/sai, giao tiếp hoặc quyền riêng tư; quản trị xác minh, phản hồi, đóng/mở lại; thành viên xem phản ánh của mình |
| Sáu nhóm đo lường, Bảng 26 | Bảng số liệu từ dữ liệu lưu thực tế, giải thích mẫu số/giới hạn; không thay dữ liệu thiếu bằng 4,8/5 hoặc 100% |

Việc tiếp nhận hồ sơ không tự cấp quyền ambassador; điều phối viên vẫn quản lý vai trò qua trang Đại sứ. Đánh giá tạm nghỉ áp dụng cho luồng phân công mới, không tự khóa tài khoản hay thay trạng thái online. Ghi nhận công việc không tự cộng điểm vào ví; cơ chế thưởng hiện có vẫn độc lập.

## Những gì vẫn cần dữ liệu/quy trình vận hành

- Các chức năng chat, AI gợi ý, hồ sơ đại sứ, lịch hẹn, UGC và ví điểm sẵn có được giữ lại. Các sửa đổi chuyển tuyến và khảo sát đang có trong working tree trước đợt này không bị gộp vào commit mới.
- Tỷ lệ tìm đúng nhu cầu và tỷ lệ duy trì theo kỳ cần thiết kế thu thập mẫu/cohort, chưa gán số liệu giả. Bảng mới ghi rõ “Chưa đo”.
- Điểm đánh giá hội thoại là `rating` hiện có, không được gọi thay cho hai thang đo rõ ràng/hữu ích. Chưa có tích hợp CRM ngoài, xác minh tự động nguồn trường, email thực gửi hay cấp chứng chỉ mới trong đợt này.
- Quyền trường xác nhận chính sách phải do người có trách nhiệm thực hiện. Phần mềm chỉ ghi nhận quyết định và ngăn dùng bản hết hạn/đã thay đổi.
- Dữ liệu mẫu hiện có vẫn xuất hiện trong thống kê. Cần kế hoạch thay dữ liệu mẫu trước vận hành thật, không xóa tự động.

## Vận hành và triển khai

1. Sao lưu SQLite và cấu hình/key mã hóa theo quy trình triển khai hiện có.
2. Cập nhật code. Các bảng bổ sung được tạo tự động, không xóa hoặc ghi đè bảng cũ.
3. Mở Vận hành đại sứ → Nguồn thông tin để người phụ trách kiểm chứng nguồn. **Nguồn cũ không tự được duyệt; chatbot có thể chưa trả lời được các câu chính sách cho đến khi hoàn tất bước này.** Các nguồn bị sửa qua Super Admin phải được xác nhận lại.
4. Sinh viên đăng nhập, mở Hành trình đại sứ. Quản trị tiếp nhận hồ sơ và giao việc sau ba bài định hướng.
5. Định kỳ xem phản ánh, nguồn đến hạn và kết quả công việc. Không coi phản ánh là sự cố đã xác minh, hoặc tin chưa gắn cờ là nội dung chắc chắn an toàn.

## Kiểm thử

`php tests/ambassador-program.php` dùng SQLite trong bộ nhớ, không đụng `data/p2p_cmc.sqlite`. Bao gồm migration lặp lại, kiểm tra quyền, consent, tiến độ học, trạng thái công việc, nguồn bị sửa/hết hạn/tắt, URL không an toàn, phản ánh và thống kê thiếu dữ liệu.

Kiểm thử UI dùng `tests/program-browser-router.php` trên loopback với biến `PROGRAM_QA_DATABASE` trỏ vào thư mục `/private/tmp/ch4-qa.*` riêng. Router chỉ hoạt động với PHP built-in server và biến môi trường kiểm thử; trả 404 khi gọi trực tiếp trên hosting. Không chạy router này trên môi trường public.

Đã kiểm tra 19 route quản trị/sinh viên/widget ở 390px: không lỗi PHP trong trang, không tràn ngang document. Đã xem trực quan trang chương trình, nguồn và chỉ số ở desktop/mobile; luồng UI hồ sơ → đào tạo → duyệt → giao → nộp → ghi nhận và xác nhận nguồn chạy trên dữ liệu QA. Đây không phải chứng nhận hoàn toàn hết mọi lỗi ở mọi trình duyệt.

## Quyết định thiết kế theo Taste

Biến thể thiết kế 3/10, chuyển động 2/10, mật độ 5/10: mở rộng thận trọng sản phẩm hiện có, không thiết kế lại các hình khóa. Giữ Bootstrap và Bootstrap Icons để tránh trộn hệ thống. Taste áp dụng cho phân cấp chữ, khoảng cách, tương phản, thành phần có trạng thái và responsive; các quy tắc landing page về ảnh hero, logo wall, bento/motion không áp dụng cho trang nghiệp vụ này. Không dùng Impeccable.

Pre-flight: một theme sáng trong workspace, xanh CMC làm màu nhấn; disclosure cho danh sách dài; trạng thái không chỉ dựa vào màu; label và focus rõ; không animation bổ sung; không ảnh giả, không số liệu bịa. Các thay đổi chung được cô lập khỏi route của hình 5–8.
