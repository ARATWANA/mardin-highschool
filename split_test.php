<?php
$f = 'C:/xampp/htdocs/mardin-highschool/student/quiz-take.php';
$c = file_get_contents($f);

// Split into chunks and test each chunk
$chunks = [
    'first_100' => substr($c, 0, 100),
    'first_500' => substr($c, 0, 500),
    'first_1000' => substr($c, 0, 1000),
    'first_2000' => substr($c, 0, 2000),
    'first_3000' => substr($c, 0, 3000),
    'first_5000' => substr($c, 0, 5000),
    'first_8000' => substr($c, 0, 8000),
    'first_12000' => substr($c, 0, 12000),
    'first_16000' => substr($c, 0, 16000),
    'full' => $c,
];

foreach ($chunks as $name => $data) {
    // Find last <?php and truncate after it
    $lastPhp = strrpos($data, '<?php');
    if ($lastPhp !== false) {
        // Find last ?> after that
        $lastClose = strpos($data, '?>', $lastPhp);
        if ($lastClose !== false) {
            $test = substr($data, 0, $lastClose + 2);
        } else {
            $test = $data;
        }
    } else {
        $test = $data;
    }
    
    $tmpFile = 'C:/xampp/tmp/test_' . $name . '.php';
    file_put_contents($tmpFile, "<?php\n" . "?> test\n"); // dummy
    file_put_contents($tmpFile, $test);
    
    $output = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($tmpFile) . ' 2>&1');
    echo "$name: " . (str_contains($output, 'No syntax errors') ? 'OK' : 'FAIL') . "\n";
    if (str_contains($output, 'No syntax errors') === false) {
        echo "  " . str_replace("\n", " | ", trim($output)) . "\n";
    }
    unlink($tmpFile);
}
