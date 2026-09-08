<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/WidgetChatAssistant.php';

$method = new ReflectionMethod(WidgetChatAssistant::class, 'normalizeSuggestedQuestion');
$cases = [
    'Bạn đang quan tâm khía cạnh nào của IoT?' => 'IoT có những khía cạnh nào để mình tìm hiểu?',
    'Bạn muốn xem học phí ngành nào?' => 'Mình muốn xem học phí ngành nào?',
    'Bạn cần hỗ trợ phần nào?' => 'Mình cần hỗ trợ phần nào?',
    'Bạn đang phân vân giữa hai ngành nào?' => 'Mình đang phân vân giữa hai ngành nào?',
    'Bạn muốn mình so sánh hai ngành không?' => 'Giúp mình so sánh hai ngành',
    'Bạn có muốn xem học phí ngành này không?' => 'Mình muốn xem học phí ngành này',
    'Bạn thích học theo hướng nào?' => '',
    'Giúp mình so sánh hai ngành' => 'Giúp mình so sánh hai ngành',
];

foreach ($cases as $input => $expected) {
    $actual = $method->invoke(null, $input);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$input}\nExpected: {$expected}\nActual: {$actual}\n");
        exit(1);
    }
}

echo "PASS suggested questions stay in the student's voice.\n";
