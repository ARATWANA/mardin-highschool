<?php
function analyzeFile($f) {
    $c = file_get_contents($f);
    echo "=== $f ===\n";
    
    // Find all PHP control structures in template blocks
    preg_match_all('/<\?php\s*(if|elseif|else|endif|foreach|endforeach|for|endfor|while|endwhile|switch|endswitch)\b/S', $c, $matches);
    
    $counts = array_count_values($matches[1]);
    foreach (['if', 'elseif', 'else', 'endif', 'foreach', 'endforeach', 'for', 'endfor', 'while', 'endwhile'] as $kw) {
        if (isset($counts[$kw])) {
            echo "  $kw: " . $counts[$kw] . "\n";
        }
    }
    
    // Also check brace-style blocks
    preg_match_all('/\bif\s*\(/', $c, $ifMatches);
    preg_match_all('/\}/', $c, $closeMatches);
    preg_match_all('/\{/', $c, $openMatches);
    echo "  brace if(: " . count($ifMatches[0]) . "\n";
    echo "  brace {: " . count($openMatches[0]) . "\n";
    echo "  brace }: " . count($closeMatches[0]) . "\n";
}

analyzeFile('C:/xampp/htdocs/mardin-highschool/teacher/homework.php');
analyzeFile('C:/xampp/htdocs/mardin-highschool/teacher/quiz.php');
analyzeFile('C:/xampp/htdocs/mardin-highschool/teacher/quiz-results.php');
