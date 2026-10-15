<?php
$files = [
    'C:\xampp\htdocs\mardin-highschool\chat.php',
    'C:\xampp\htdocs\mardin-highschool\teacher/homework.php',
    'C:\xampp\htdocs\mardin-highschool/student/homework.php',
    'C:\xampp\htdocs/mardin-highschool/teacher/quiz.php',
    'C:\xampp\htdocs/mardin-highschool/student/quiz.php',
    'C:\xampp\htdocs/mardin-highschool/student/quiz-take.php',
    'C:\xampp\htdocs/mardin-highschool/student/quiz-results.php',
    'C:\xampp\htdocs/mardin-highschool/teacher/quiz-results.php',
    'C:\xampp\htdocs/mardin-highschool/student/quiz-submit.php',
    'C:\xampp\htdocs/mardin-highschool/api/upload.php',
    'C:\xampp\htdocs/mardin-highschool/api/quiz.php',
    'C:\xampp\htdocs/mardin-highschool/api/chat-request.php',
];

foreach ($files as $f) {
    $output = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($f) . ' 2>&1');
    $ok = stripos($output, 'No syntax errors') !== false;
    echo ($ok ? 'OK  ' : 'ERR ') . basename(dirname($f)) . '/' . basename($f) . "\n";
    if (!$ok) echo "  $output\n";
}
