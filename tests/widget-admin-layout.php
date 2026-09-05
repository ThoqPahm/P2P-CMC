<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_error_handler(static function(int $severity, string $message): never { throw new RuntimeException($message); });
function require_auth(array $roles): void {}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function csrf_field(): string { return '<input name="csrf_token" value="fixture" type="hidden">'; }
function rows(string $sql): array { return $GLOBALS['appointmentsFixture']; }
function scalar(string $sql): int { return 2; }
function renderWidgetAdmin(array $appointments, string $host, string $path='/index.php', bool $https=false): string {
    $GLOBALS['appointmentsFixture']=$appointments;
    $_SERVER['HTTP_HOST']=$host;
    $_SERVER['SCRIPT_NAME']=$path;
    $_SERVER['HTTPS']=$https?'on':'off';
    ob_start(); require __DIR__.'/../pages/admin/widget.php'; return ob_get_clean();
}
$checks=0;
function check(bool $ok, string $label): void { if(!$ok) throw new RuntimeException('FAIL: '.$label); $GLOBALS['checks']++; echo "PASS: $label\n"; }
$empty=renderWidgetAdmin([], '127.0.0.1:8000');
check(str_contains($empty, 'Chưa có lịch tư vấn'), 'empty schedule');
check(str_contains($empty, 'Đây là mã nhúng từ máy local'), 'local URL warning');
check(!str_contains($empty, 'Bộ lọc khả dụng'), 'no hard-coded filter metric');
$item=['id'=>9,'student_name'=>'QA <script>','email'=>'qa@example.test','phone'=>'0123456789','ambassador_name'=>'Đại sứ QA','ambassador_major'=>'Ngành thử nghiệm','preferred_at'=>'2026-09-10 10:30:00','question'=>"Câu hỏi dài\n<script>alert(1)</script>",'status'=>'pending'];
$html=renderWidgetAdmin([$item], 'school.example', '/portal/index.php', true);
check(str_contains($html, 'https://school.example/portal/assets/js/eambassador-widget.js?v=2'), 'production embed path unchanged');
check(str_contains($html, 'data-position=&quot;right&quot; async'), 'embed configuration unchanged');
check(!str_contains($html, 'Đây là mã nhúng từ máy local'), 'no local warning on production');
check(str_contains($html, '<iframe src="https://school.example/portal/widget"'), 'clean widget iframe target');
check(!str_contains($html, '<script>'), 'user-provided content escaped');
check(str_contains($html, '<details class="appointment-question">'), 'full question disclosure');
check(str_contains($html, 'for="appointment-status-9"'), 'status label associated');
check(str_contains($html, 'actions.php?action=update_appointment_status') && str_contains($html, 'name="appointment_id" value="9"'), 'status action retained');
foreach(['pending','confirmed','completed','cancelled'] as $status) check(str_contains($html, 'value="'.$status.'"'), 'status '.$status.' retained');
echo "$checks checks passed.\n";
