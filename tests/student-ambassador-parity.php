<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

foreach (['dashboard.php', 'campaigns.php', 'submissions.php', 'performance.php', 'wallet.php', 'copilot.php'] as $page) {
    $source = file_get_contents($root . '/pages/student/' . $page);
    $check(str_contains($source, "require_auth(['student', 'ambassador'])"), "$page must be shared by students and ambassadors");
}

$inbox = file_get_contents($root . '/pages/student/inbox.php');
$check(str_contains($inbox, "require_auth(['ambassador'])"), 'Consultation inbox must remain ambassador-only');

$submissions = file_get_contents($root . '/pages/student/submissions.php');
$actions = file_get_contents($root . '/actions.php');
$check(!str_contains($submissions, "user()['role'] === 'ambassador'"), 'Shared submission UI must not branch by member role');
$check(str_contains($actions, "case 'submit_blog':\n            require_auth(['student', 'ambassador']);"), 'Blog submission must accept both member roles');

$program = file_get_contents($root . '/pages/program.php');
$check(str_contains($program, '<?php if ($student): ?><details class="program-record"'), 'Join form must be student-only');
$header = file_get_contents($root . '/includes/header.php');
$check(str_contains($header, "'ambassador', 'student' => 'Không gian sinh viên'"), 'Both member roles must use one workspace label');

echo "PASS student and ambassador workspace parity with explicit role exceptions.\n";
