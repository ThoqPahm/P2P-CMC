<?php
// This fragment is included only after pages/program.php has authenticated the actor.
if (!isset($reports,$form,$admin)) { http_response_code(404); exit; }
$categories=['information'=>'Thông tin cần sửa','communication'=>'Chất lượng giao tiếp','privacy'=>'Quyền riêng tư'];
?>
<div class="program-list">
<?php if (!$reports): ?><div class="program-empty">Chưa có phản ánh được ghi nhận.</div><?php endif; ?>
<?php foreach ($reports as $report): ?>
<details class="program-record"><summary><span><strong><?= e($categories[$report['category']]??'Phản ánh') ?></strong><small><?= e($report['name']) ?> · <?= e($report['created_at']) ?> UTC</small></span><span class="program-status <?= $report['status']==='resolved'?'is-complete':'' ?>"><?= $report['status']==='resolved'?'Đã xử lý':'Đang chờ' ?></span></summary><div class="program-record-body"><p class="program-prose"><?= nl2br(e($report['detail'])) ?></p><?php if ($report['response']): ?><div class="program-note">Phản hồi: <?= e($report['response']) ?></div><?php endif; ?>
<?php if ($admin): ?><form action="program-actions.php" method="post" class="program-form"><?php $form('resolve_report',(int)$report['id']); ?><label>Trạng thái xử lý<select class="form-select" name="status"><option value="open">Tiếp tục xác minh</option><option value="resolved">Đã xử lý</option></select></label><label>Kết quả xác minh và biện pháp cải thiện<textarea name="note" class="form-control" required maxlength="2000"><?= e($report['response']) ?></textarea></label><button class="btn btn-brand">Lưu phản hồi</button></form><?php endif; ?>
</div></details>
<?php endforeach; ?>
</div>
