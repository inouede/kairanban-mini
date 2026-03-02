<?php
/**
 * webpush.php構造確認ツール
 * 利用可能な関数を確認
 */

require_once __DIR__ . '/config.php';

checkAdmin();

echo "=== webpush.php構造確認 ===\n\n";

$webpushFile = __DIR__ . '/webpush.php';

if (!file_exists($webpushFile)) {
    echo "❌ webpush.phpが見つかりません\n";
    echo "パス: {$webpushFile}\n";
    exit;
}

echo "✅ webpush.phpを発見\n\n";

$content = file_get_contents($webpushFile);

echo "【関数定義】\n";
preg_match_all('/function\s+(\w+)\s*\(/i', $content, $matches);
if (!empty($matches[1])) {
    foreach ($matches[1] as $func) {
        echo "- {$func}()\n";
        
        // 関数のシグネチャを取得
        $pattern = '/function\s+' . preg_quote($func, '/') . '\s*\(([^)]*)\)/i';
        if (preg_match($pattern, $content, $sigMatch)) {
            echo "  引数: ({$sigMatch[1]})\n";
        }
    }
} else {
    echo "関数定義が見つかりません\n";
}

echo "\n【require/include】\n";
preg_match_all('/(require|include)(_once)?\s+[\'"](.+?)[\'"]/i', $content, $matches);
if (!empty($matches[3])) {
    foreach ($matches[3] as $file) {
        echo "- {$file}\n";
    }
} else {
    echo "なし\n";
}

echo "\n【use文（名前空間）】\n";
preg_match_all('/use\s+([^;]+);/i', $content, $matches);
if (!empty($matches[1])) {
    foreach ($matches[1] as $use) {
        echo "- {$use}\n";
    }
} else {
    echo "なし\n";
}

echo "\n=== 確認完了 ===\n";
