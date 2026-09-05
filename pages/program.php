<?php
require_auth(['admin','student','ambassador']);
if (user()['status'] !== 'active') { http_response_code(403); exit('Tài khoản hiện không hoạt động.'); }
$admin = user()['role'] === 'admin';
$pageTitle = $admin ? 'Vận hành đại sứ' : 'Hành trình đại sứ';
$section = $admin && in_array($_GET['tab']??'', ['knowledge','quality','reports'],true) ? $_GET['tab'] : 'members';
$form = static function(string $action, int $id=0) use ($section): void { ?>
    <input type="hidden" name="action" value="<?= e($action) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tab" value="<?= e($section) ?>"><?= csrf_field() ?>
<?php };
$label = static fn(string $key): string => AmbassadorProgram::LABELS[$key] ?? $key;
?>
<div class="program-workspace">
    <header class="program-heading">
        <div><p class="program-eyebrow">CMC · Cộng đồng đại sứ</p><h2><?= $admin ? 'Từ định hướng đến đóng góp.' : 'Một lộ trình, từng bước rõ ràng.' ?></h2><p><?= $admin ? 'Điều phối con người, xác nhận thông tin và nhìn lại chất lượng tư vấn.' : 'Chuẩn bị kiến thức, nhận việc phù hợp và cùng cải thiện trải nghiệm tư vấn.' ?></p></div>
        <a class="btn btn-outline-brand" href="index.php?page=dashboard">Về tổng quan <i class="bi bi-arrow-up-right"></i></a>
    </header>
    <?php if ($admin): ?>
    <nav class="program-tabs" aria-label="Vận hành đại sứ">
        <?php foreach (['members'=>'Thành viên & công việc','knowledge'=>'Nguồn thông tin','quality'=>'Chất lượng tư vấn','reports'=>'Phản ánh & cải thiện'] as $key=>$name): ?><a <?= $section===$key?'aria-current="page"':'' ?> href="index.php?page=ambassador-program&tab=<?= e($key) ?>"><?= e($name) ?></a><?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($section === 'knowledge'): $entries=AmbassadorProgram::knowledge($db); ?>
    <div class="program-note"><i class="bi bi-info-circle"></i><p>Chỉ nguồn <strong>chính thức, đã xác nhận, còn hạn và đang bật</strong> được đưa vào câu trả lời của chatbot. Sửa nội dung ở Super Admin sẽ làm xác nhận cũ mất hiệu lực. Người duyệt chịu trách nhiệm kiểm tra nguồn; hệ thống không tự xác minh tính đúng của tài liệu.</p></div>
    <?php if (is_super_admin()): ?><p><a class="text-link" href="index.php?page=super-admin#knowledge">Thêm hoặc sửa nội dung gốc <i class="bi bi-arrow-up-right"></i></a></p><?php endif; ?>
    <div class="program-list">
    <?php foreach ($entries as $entry): ?>
    <details class="program-record">
        <summary><span><small><?= e($entry['category']) ?></small><strong><?= e($entry['title']) ?></strong></span><span class="program-status <?= $entry['usable']?'is-complete':'' ?>"><?= $entry['usable'] ? ((int)$entry['is_active']===1?'Đã xác nhận':'Đã xác nhận · đang tắt') : 'Cần rà soát' ?></span></summary>
        <div class="program-record-body">
            <p class="program-prose"><?= nl2br(e($entry['content'])) ?></p>
            <form action="program-actions.php" method="post" class="program-form"><?php $form('save_source',(int)$entry['id']); ?>
                <div class="program-fields">
                    <label>Loại thông tin<select class="form-select" name="kind"><?php foreach (['official','experience'] as $kind): ?><option value="<?= $kind ?>" <?= $entry['kind']===$kind?'selected':'' ?>><?= e($label($kind)) ?></option><?php endforeach; ?></select></label>
                    <label>Nguồn kiểm chứng (URL)<input class="form-control" type="url" name="source_url" required maxlength="1000" value="<?= e($entry['source_url']) ?>" placeholder="https://cmcu.edu.vn/…"></label>
                    <label>Phạm vi áp dụng<input class="form-control" name="scope" required maxlength="1000" value="<?= e($entry['scope']) ?>" placeholder="Ví dụ: khóa tuyển sinh, ngành, đối tượng"></label>
                    <label>Rà soát lại chậm nhất<input class="form-control" type="date" name="valid_until" required min="<?= date('Y-m-d') ?>" value="<?= e($entry['valid_until']) ?>"></label>
                </div>
                <label>Quyết định<select class="form-select" name="state"><option value="draft">Lưu để tiếp tục rà soát</option><option value="approved">Tôi đã kiểm tra nguồn và xác nhận nội dung</option></select></label>
                <div class="program-form-footer"><small><?= $entry['confirmer'] ? 'Xác nhận gần nhất: '.e($entry['confirmer']).' · '.e($entry['confirmed_at']) : 'Chưa có người xác nhận trong luồng này.' ?></small><button class="btn btn-brand">Lưu kiểm chứng</button></div>
            </form>
            <?php $history=rows("SELECT a.*,u.name FROM program_audit a JOIN users u ON u.id=a.actor_id WHERE entity='knowledge' AND entity_id=? ORDER BY a.id DESC LIMIT 10",[$entry['id']]); if ($history): ?>
            <details class="program-history"><summary>Lịch sử kiểm chứng · <?= count($history) ?> lần gần nhất</summary><?php foreach ($history as $record): $snapshot=json_decode($record['snapshot'],true)??[]; ?><article><strong><?= e($record['name']) ?></strong><small><?= e($record['created_at']) ?> UTC · <?= e($label($snapshot['state']??'draft')) ?></small><p><?= e($snapshot['scope']??'') ?> · <?= e($snapshot['source_url']??'') ?></p><details><summary>Nội dung tại thời điểm duyệt</summary><p class="program-prose"><?= nl2br(e($snapshot['entry']['content']??'')) ?></p></details></article><?php endforeach; ?></details>
            <?php endif; ?>
        </div>
    </details>
    <?php endforeach; ?>
    <?php if (!$entries): ?><div class="program-empty">Chưa có nội dung gốc. Super Admin có thể thêm tài liệu trước khi kiểm chứng.</div><?php endif; ?>
    </div>

    <?php elseif ($section === 'quality'): $m=AmbassadorProgram::metrics($db); ?>
    <div class="program-note"><i class="bi bi-database"></i><p>Số liệu toàn thời gian trong cơ sở dữ liệu hiện tại, bao gồm dữ liệu mẫu nếu chưa xóa. “Chưa đo” không có nghĩa là 0 hoặc 100%. Các chỉ số dưới đây mô tả hoạt động, không chứng minh hiệu quả tuyển sinh.</p></div>
    <div class="program-metrics">
    <?php $groups=[
        ['Tiếp cận phù hợp', ['Ngành có đại sứ hoạt động'=>$m['majors'].' ngành','Tỷ lệ tìm đúng nhu cầu'=>'Chưa đo'], 'Số ngành chỉ phản ánh độ phủ hồ sơ; chưa có khảo sát xác nhận kết nối phù hợp.'],
        ['Tốc độ & liền mạch', ['Phản hồi đầu của đại sứ'=>$m['first_reply_minutes']===null?'Chưa có phản hồi':$m['first_reply_minutes'].' phút','Hội thoại đã mở'=>$m['conversations']], 'Trung bình từ tin đầu của học sinh đến tin đầu tiếp theo của đại sứ; không tính AI và các phiên chưa được trả lời.'],
        ['Rõ ràng & tin cậy', ['Nguồn đủ điều kiện cho AI'=>$m['approved_sources'].' / '.$m['total_sources'],'Điểm đánh giá hội thoại'=>$m['rating']===null?'Chưa có đánh giá':number_format((float)$m['rating'],1).' / 5'], 'Điểm từ '.$m['rated'].' hội thoại có đánh giá 1–5; không thay thế hai thang đo rõ ràng và hữu ích riêng.'],
        ['Tương tác có ý nghĩa', ['Hội thoại'=>$m['conversations'],'Yêu cầu đặt lịch'=>$m['appointments']], 'Đếm bản ghi yêu cầu; không đồng nghĩa cuộc hẹn đã diễn ra hoặc học sinh hài lòng.'],
        ['Tham gia của đại sứ', ['Hoàn thành định hướng'=>$m['trained'].' thành viên','Công việc được ghi nhận'=>$m['completed_tasks'].' / '.$m['tasks']], 'Định hướng gồm ba nội dung. Công việc chỉ được tính hoàn thành sau nhận xét của quản trị viên; chưa đo tỷ lệ duy trì theo kỳ.'],
        ['An toàn & trách nhiệm', ['Tin nhắn gắn cờ'=>$m['flagged'].' / '.$m['messages'],'Phản ánh chưa xử lý'=>$m['open_reports'],'Phản ánh về quyền riêng tư'=>$m['privacy_reports']], 'Đếm phản ánh trong hệ thống, không phải số sự cố đã được xác minh. Không gắn cờ không có nghĩa là an toàn.'],
    ]; foreach ($groups as $i=>[$title,$values,$description]): ?>
    <section class="program-metric"><h3><?= e($title) ?></h3><dl><?php foreach ($values as $name=>$value): ?><div><dt><?= e($name) ?></dt><dd><?= e((string)$value) ?></dd></div><?php endforeach; ?></dl><p><?= e($description) ?></p></section>
    <?php endforeach; ?>
    </div>
    <div class="program-form-footer"><a class="btn btn-outline-brand" href="index.php?page=admin-performance&tab=ugc">Hiệu quả nội dung UGC</a><a class="btn btn-brand" href="index.php?page=admin-moderation">Mở hàng đợi hội thoại</a></div>

    <?php elseif ($section === 'reports'): ?>
    <h3 class="h5 mt-4">Phản ánh cần người phụ trách xử lý</h3>
    <p class="program-muted">Phản ánh chưa đồng nghĩa với sự cố đã được xác minh. Đọc nội dung, xác minh và ghi lại biện pháp xử lý trước khi đóng.</p>
    <?php $reports=rows('SELECT r.*,u.name FROM program_reports r JOIN users u ON u.id=r.reporter_id ORDER BY CASE WHEN r.status=\'open\' THEN 0 ELSE 1 END,r.id DESC'); require __DIR__.'/program-reports.php'; ?>
    <?php elseif ($admin):
        $members=rows('SELECT a.*,u.name,u.major,u.role,(SELECT COUNT(*) FROM ambassador_training t WHERE t.user_id=a.user_id) AS trained FROM ambassador_applications a JOIN users u ON u.id=a.user_id ORDER BY a.updated_at DESC');
        $tasks=rows('SELECT t.*,u.name,m.name AS mentor FROM ambassador_tasks t JOIN users u ON u.id=t.user_id JOIN users m ON m.id=t.mentor_id ORDER BY t.id DESC');
    ?>
    <div class="program-section-title"><h3>Hồ sơ tham gia</h3><span><?= count($members) ?> hồ sơ</span></div>
    <p class="program-muted">Tiếp nhận hồ sơ không tự đổi quyền tài khoản hoặc cấp danh hiệu. Quản lý vai trò vẫn nằm ở <a href="index.php?page=admin-ambassadors">trang Đại sứ</a>.</p>
    <?php if (!$members): ?><div class="program-empty"><i class="bi bi-people"></i><h3>Sẵn sàng đón thành viên đầu tiên</h3><p>Sinh viên đăng nhập và mở “Hành trình đại sứ” để gửi hồ sơ, học định hướng và nhận công việc.</p></div><?php endif; ?>
    <div class="program-list"><?php foreach ($members as $member): ?>
        <details class="program-record"><summary><span><strong><?= e($member['name']) ?></strong><small><?= e($member['major']) ?> · Định hướng <?= (int)$member['trained'] ?>/3</small></span><span class="program-status"><?= e($label($member['status'])) ?> · <?= e($label($member['participation'])) ?></span></summary><div class="program-record-body">
            <dl class="program-details"><?php foreach (['motivation'=>'Động lực','topics'=>'Chủ đề tư vấn','skills'=>'Kỹ năng','availability'=>'Thời gian tham gia'] as $key=>$name): ?><div><dt><?= $name ?></dt><dd><?= nl2br(e($member[$key])) ?></dd></div><?php endforeach; ?></dl>
            <form action="program-actions.php" method="post" class="program-form"><?php $form('review_application',(int)$member['user_id']); ?><div class="program-fields"><label>Tiếp nhận hồ sơ<select class="form-select" name="status"><option value="approved">Tiếp nhận</option><option value="rejected">Chưa phù hợp</option></select></label><label>Phản hồi cho thành viên<input class="form-control" name="note" required maxlength="2000" value="<?= e($member['review_note']) ?>"></label></div><button class="btn btn-brand">Lưu kết quả hồ sơ</button></form>
            <details class="program-history"><summary>Đánh giá định kỳ & nguyện vọng</summary>
                <?php $reviews=rows('SELECT r.*,u.name FROM ambassador_reviews r JOIN users u ON u.id=r.author_id WHERE r.user_id=? ORDER BY r.id DESC LIMIT 10',[$member['user_id']]); foreach ($reviews as $review): ?><article><strong><?= e($review['name']) ?> · <?= e($label($review['decision'])) ?></strong><small><?= e($review['created_at']) ?> UTC</small><p><?= e($review['note']) ?></p></article><?php endforeach; ?>
                <form action="program-actions.php" method="post" class="program-form"><?php $form('review_member',(int)$member['user_id']); ?><label>Quyết định tham gia<select name="decision" class="form-select"><option value="active">Tiếp tục tham gia</option><option value="paused">Tạm nghỉ</option><option value="role_change">Cần bố trí vai trò khác</option></select></label><label>Nhận xét và bước tiếp theo<textarea class="form-control" name="note" required maxlength="2000"></textarea></label><button class="btn btn-outline-brand">Lưu đánh giá định kỳ</button></form>
            </details>
        </div></details>
    <?php endforeach; ?></div>
    <section class="program-panel"><div class="program-section-title"><h3>Giao công việc</h3><span>Người tạo là người hướng dẫn</span></div>
        <?php $eligible=array_filter($members,static fn($m)=>$m['status']==='approved'&&$m['participation']==='active'&&(int)$m['trained']===3); if ($eligible): ?>
        <form action="program-actions.php" method="post" class="program-form"><?php $form('assign_task'); ?>
            <div class="program-fields"><label>Thành viên<select class="form-select" name="id"><?php foreach ($eligible as $m): ?><option value="<?= (int)$m['user_id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></label><label>Loại công việc<select class="form-select" name="kind"><option value="content">Nội dung</option><option value="consultation">Tư vấn</option><option value="event">Sự kiện</option></select></label><label>Tên công việc<input class="form-control" name="title" required maxlength="160"></label><label>Hạn hoàn thành<input class="form-control" type="date" name="due_date" min="<?= date('Y-m-d') ?>" required></label></div>
            <label>Mục tiêu, nguồn cần dùng và tiêu chí hoàn thành<textarea class="form-control" name="brief" required maxlength="4000" rows="3"></textarea></label><button class="btn btn-brand">Giao công việc</button>
        </form><?php else: ?><p class="program-muted">Chưa có thành viên được tiếp nhận và hoàn thành cả ba nội dung định hướng. Công việc sẽ mở sau bước này.</p><?php endif; ?>
    </section>
    <?php else:
        $application=rows('SELECT * FROM ambassador_applications WHERE user_id=?',[user()['id']])[0]??null;
        $completed=array_column(rows('SELECT module FROM ambassador_training WHERE user_id=?',[user()['id']]),'module');
        $tasks=rows('SELECT t.*,u.name AS mentor FROM ambassador_tasks t JOIN users u ON u.id=t.mentor_id WHERE t.user_id=? ORDER BY t.id DESC',[user()['id']]);
    ?>
    <div class="program-steps" aria-label="Các bước tham gia"><span>01 · Hồ sơ</span><span>02 · Định hướng <?= count($completed) ?>/3</span><span>03 · Công việc</span><span>04 · Phản hồi</span></div>
    <details class="program-record" <?= !$application?'open':'' ?>><summary><span><strong>Hồ sơ & mong muốn của bạn</strong><small><?= $application?e($label($application['status'])):'Bắt đầu từ những điều bạn có thể đóng góp' ?></small></span><i class="bi bi-chevron-down"></i></summary><div class="program-record-body">
        <?php if ($application && $application['review_note']): ?><p class="program-note">Phản hồi hồ sơ: <?= e($application['review_note']) ?></p><?php endif; ?>
        <form action="program-actions.php" method="post" class="program-form"><?php $form('apply'); ?><label>Vì sao bạn muốn tham gia?<textarea class="form-control" name="motivation" required maxlength="2000" rows="3"><?= e($application['motivation']??'') ?></textarea></label><div class="program-fields"><?php foreach (['topics'=>'Chủ đề bạn có thể chia sẻ','skills'=>'Kỹ năng và kinh nghiệm','availability'=>'Thời gian có thể tham gia'] as $key=>$name): ?><label><?= $name ?><input class="form-control" name="<?= $key ?>" required maxlength="500" value="<?= e($application[$key]??'') ?>"></label><?php endforeach; ?></div><label class="program-check"><input type="checkbox" name="consent" value="1" required><span>Tôi đồng ý dùng hồ sơ này để xét tham gia và điều phối hoạt động đại sứ. Chỉ bản thân tôi và quản trị viên được xem. Không nhập giấy tờ định danh hoặc dữ liệu nhạy cảm.</span></label><button class="btn btn-brand"><?= $application?'Cập nhật hồ sơ':'Gửi hồ sơ tham gia' ?></button></form>
    </div></details>
    <div class="program-section-title"><h3>Định hướng trước khi bắt đầu</h3><span><?= count($completed) ?>/3 hoàn thành</span></div>
    <div class="program-list"><?php foreach (AmbassadorProgram::MODULES as $key=>[$title,$content,$question,$answers]): ?>
        <details class="program-record"><summary><span><strong><?= e($title) ?></strong></span><span class="program-status <?= in_array($key,$completed,true)?'is-complete':'' ?>"><?= in_array($key,$completed,true)?'Đã hoàn thành':'Bắt đầu học' ?></span></summary><div class="program-record-body"><p class="program-prose"><?= e($content) ?></p><?php if (!in_array($key,$completed,true)): ?><form action="program-actions.php" method="post" class="program-form"><?php $form('complete_training'); ?><input type="hidden" name="module" value="<?= e($key) ?>"><fieldset><legend><?= e($question) ?></legend><?php $order=[1,0,2]; if ($key==='privacy') $order=[2,1,0]; foreach ($order as $answer): ?><label class="program-check"><input type="radio" name="answer" value="<?= $answer ?>" required><span><?= e($answers[$answer]) ?></span></label><?php endforeach; ?></fieldset><button class="btn btn-brand">Kiểm tra & hoàn thành</button></form><?php endif; ?></div></details>
    <?php endforeach; ?></div>
    <?php if ($application): ?><section class="program-panel"><div class="program-section-title"><h3>Nguyện vọng & phản hồi</h3><span><?= e($label($application['participation'])) ?></span></div><form action="program-actions.php" method="post" class="program-form"><?php $form('member_feedback'); ?><label>Bạn muốn tiếp tục thế nào?<select class="form-select" name="decision"><option value="active">Tiếp tục tham gia</option><option value="paused">Xin tạm nghỉ</option><option value="role_change">Đổi loại công việc / vai trò</option></select></label><label>Điều đang thuận lợi, khó khăn hoặc cần hỗ trợ<textarea class="form-control" name="note" required maxlength="2000" rows="3"></textarea></label><button class="btn btn-outline-brand">Gửi người điều phối</button></form>
        <?php $reviews=rows('SELECT r.*,u.name FROM ambassador_reviews r JOIN users u ON u.id=r.author_id WHERE r.user_id=? ORDER BY r.id DESC LIMIT 10',[user()['id']]); foreach ($reviews as $review): ?><article class="program-review"><strong><?= e($review['name']) ?> · <?= e($label($review['decision'])) ?></strong><small><?= e($review['created_at']) ?> UTC</small><p><?= e($review['note']) ?></p></article><?php endforeach; ?>
    </section><?php endif; ?>
    <?php endif; ?>

    <?php if ($section==='members'): ?>
    <div class="program-section-title"><h3><?= $admin?'Theo dõi công việc':'Công việc của bạn' ?></h3><span><?= count($tasks) ?> công việc</span></div>
    <?php if (!$tasks): ?><div class="program-empty">Chưa có công việc được giao. Các nhiệm vụ và nhận xét sẽ được lưu tại đây.</div><?php endif; ?>
    <div class="program-list"><?php foreach ($tasks as $task): ?><details class="program-record"><summary><span><strong><?= e($task['title']) ?></strong><small><?= $admin?e($task['name']).' · ':'' ?>Hạn <?= e($task['due_date']) ?><?= $task['due_date']<date('Y-m-d')&&!in_array($task['status'],['submitted','completed'],true)?' · Quá hạn':'' ?></small></span><span class="program-status <?= $task['status']==='completed'?'is-complete':'' ?>"><?= e($label($task['status'])) ?></span></summary><div class="program-record-body"><p class="program-muted">Người hướng dẫn: <?= e($task['mentor']) ?></p><p class="program-prose"><?= nl2br(e($task['brief'])) ?></p><?php if ($task['result']): ?><h4>Kết quả đã gửi</h4><p class="program-prose"><?= nl2br(e($task['result'])) ?></p><?php endif; ?><?php if ($task['feedback']): ?><div class="program-note">Nhận xét: <?= e($task['feedback']) ?></div><?php endif; ?>
        <?php if (!$admin&&in_array($task['status'],['assigned','revision'],true)): ?><form action="program-actions.php" method="post" class="program-form"><?php $form('submit_task',(int)$task['id']); ?><label>Kết quả, liên kết sản phẩm và điều bạn học được<textarea class="form-control" name="result" required maxlength="4000" rows="4"><?= e($task['result']) ?></textarea></label><button class="btn btn-brand">Gửi kết quả</button></form><?php endif; ?>
        <?php if ($admin&&$task['status']==='submitted'): ?><form action="program-actions.php" method="post" class="program-form"><?php $form('review_task',(int)$task['id']); ?><label>Kết quả rà soát<select class="form-select" name="status"><option value="completed">Ghi nhận hoàn thành</option><option value="revision">Yêu cầu bổ sung</option></select></label><label>Nhận xét cho thành viên<textarea class="form-control" name="note" required maxlength="2000"></textarea></label><button class="btn btn-brand">Gửi nhận xét</button></form><?php endif; ?>
    </div></details><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if (!$admin): ?>
    <section class="program-panel"><h3 class="h5">Báo thông tin cần sửa hoặc vấn đề an toàn</h3><p class="program-muted">Chỉ bạn và quản trị viên xem được phản ánh. Không dán mật khẩu, giấy tờ cá nhân hoặc toàn bộ hội thoại có thông tin nhạy cảm.</p><form action="program-actions.php" method="post" class="program-form"><?php $form('report_issue'); ?><label>Nhóm vấn đề<select name="category" class="form-select"><option value="information">Thông tin thiếu hoặc cần sửa</option><option value="communication">Chất lượng giao tiếp</option><option value="privacy">Quyền riêng tư & dữ liệu</option></select></label><label>Mô tả ngắn, vị trí gặp vấn đề và đề xuất<textarea class="form-control" name="detail" required maxlength="2000" rows="3"></textarea></label><button class="btn btn-outline-brand">Gửi phản ánh</button></form>
    <?php $reports=rows('SELECT r.*,u.name FROM program_reports r JOIN users u ON u.id=r.reporter_id WHERE r.reporter_id=? ORDER BY r.id DESC',[user()['id']]); require __DIR__.'/program-reports.php'; ?>
    </section>
    <?php endif; ?>
</div>
