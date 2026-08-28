# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Sinh viên CMC nhận nhiệm vụ truyền thông, tạo nội dung, nộp bài, theo dõi affiliate, điểm thưởng và tiến độ cá nhân.
- Đại sứ sinh viên dùng toàn bộ luồng sinh viên, đồng thời tư vấn học sinh THPT qua hội thoại P2P có kiểm soát chất lượng.
- Quản trị viên tuyển sinh và vận hành theo dõi chiến dịch, UGC, đại sứ, leads, phần thưởng và an toàn hội thoại.
- Học sinh THPT tìm đại sứ phù hợp, đọc trải nghiệm thật và bắt đầu cuộc trò chuyện hoặc đăng ký tư vấn.

## Product Purpose

CMC Connect vận hành hệ sinh thái đại sứ sinh viên số và truyền thông ngang hàng của Trường Đại học CMC. Thành công là giúp sinh viên hoàn thành nhiệm vụ rõ ràng, giúp admin kiểm soát vận hành nhanh, và giúp học sinh THPT tiếp cận trải nghiệm học tập đáng tin cậy từ người đang học.

## Positioning

Nền tảng nối liền nội dung UGC, affiliate, hội thoại P2P, kiểm duyệt và điểm thưởng trong một vòng vận hành có thể theo dõi từ sinh viên đến tuyển sinh.

## Operating Context

- Người dùng nội bộ đăng nhập theo vai trò và làm việc trong app shell trên desktop hoặc mobile web.
- Sinh viên thường xuyên xem nhiệm vụ, brief, trạng thái bài nộp, số liệu affiliate và ví điểm.
- Admin cần quét nhanh trạng thái hệ thống, xử lý danh sách, đánh giá nội dung và điều phối chính sách.
- Học sinh THPT dùng bề mặt công khai để tìm người phù hợp và trò chuyện.

## Capabilities and Constraints

- Backend PHP 8.1+, PDO và SQLite; frontend HTML5, Bootstrap 5.3, Bootstrap Icons, CSS riêng và JavaScript thuần.
- Không dùng Node.js, Python hoặc Tailwind trong source/runtime của website.
- Giữ nguyên URL, route slug, chức năng, dữ liệu, form field, phân quyền và nhãn điều hướng hiện có trong đợt redesign.
- Giao diện phải responsive từ mobile web đến desktop rộng; motion phục vụ phản hồi và chuyển trạng thái, không chặn nội dung.
- Hai luồng chuẩn của app nội bộ là sinh viên và admin, có cùng design system nhưng khác cấu trúc thông tin theo công việc.

## Brand Commitments

- Tên sản phẩm: CMC Connect / Student Connect thuộc CMC University.
- Giữ nguyên logo CMC University hiện có.
- Palette bắt buộc: CMC Navy `#002757`, CMC Blue `#008FD5`, CMC Cyan `#00DEDF`, trắng và hệ neutral lạnh.
- Không dùng cam, coral, beige, warm cream hoặc near-black làm màu nhận diện.
- Giao diện phải clean và hiện đại nhưng không được đánh đồng clean với đơn điệu hoặc thiếu cấu trúc.

## Evidence on Hand

- Nội dung, dữ liệu mẫu, vai trò và workflow nằm trong `README.md`, `app/Database.php`, `pages/`, `actions.php` và `api.php`.
- Logo và hình ảnh nhận diện hiện có nằm trong `assets/img/`.
- Không được bịa thêm khách hàng, chỉ số kinh doanh, benchmark hoặc claim thương mại.

## Product Principles

1. Công việc chính phải nhìn thấy ngay trong viewport đầu tiên.
2. Một hành động hoặc trạng thái phải dùng cùng một component vocabulary ở mọi màn hình.
3. Sinh viên cần động lực và định hướng; admin cần khả năng quét, so sánh và xử lý nhanh.
4. Brand CMC xuất hiện qua màu, nhịp và độ chính xác, không qua trang trí dư thừa.
5. Responsive là thay đổi cấu trúc, không chỉ co nhỏ desktop.

## Accessibility & Inclusion

- Duy trì semantic HTML, keyboard navigation, focus state rõ ràng và độ tương phản tối thiểu WCAG AA.
- Tôn trọng `prefers-reduced-motion`; nội dung phải hiển thị đầy đủ ngay cả khi JavaScript hoặc motion không chạy.
