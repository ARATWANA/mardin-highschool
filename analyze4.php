<?php
function analyzeFile($f) {
    $c = file_get_contents($f);
    echo "=== $f ===\n";
    preg_match_all('/<\?php\s*(if|elseif|else|endif|foreach|endforeach|for|endfor|while|endwhile|switch|endswitch)\b/S', $c, $matches);
    $counts = array_count_values($matches[1]);
    foreach (['if', 'elseif', 'else', 'endif', 'foreach', 'endforeach', 'for', 'endfor', 'while', 'endwhile'] as $kw) {
        if (isset($counts[$kw])) {
            echo "  $kw: " . $counts[$kw] . "\n";
        }
    }
}

analyzeFile('C:/xampp/htdocs/mardin-highschool/student/quiz-take.php');
