<?php

/*
 * PHPUnit JUnit hisobotidan GitHub Actions izohlari (annotations): muvaffaqiyatsiz testlar
 * "Files changed" / "Summary" sahifalarida fayl va qator bilan ko‘rinadi.
 * Ishlatish: php .github/scripts/junit-annotations.php junit.xml
 */

$file = $argv[1] ?? 'junit.xml';
if (! is_file($file)) {
    fwrite(STDERR, "{$file} topilmadi\n");
    exit(0);
}

$xml = simplexml_load_file($file);
$escape = fn (string $s) => str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $s);
$count = 0;

foreach ($xml->xpath('//testcase[failure or error]') as $case) {
    $problem = $case->failure ?? $case->error;
    $text = trim((string) $problem);
    $path = str_replace(getcwd().'/', '', (string) ($case['file'] ?? ''));
    $line = (int) ($case['line'] ?? 1);

    // Xato matnidagi birinchi "tests/...php:123" qatorini topamiz (aniqroq joy).
    if (preg_match('#(tests/[^\s:]+\.php):(\d+)#', $text, $m)) {
        [$path, $line] = [$m[1], (int) $m[2]];
    }

    $title = $case['class'].'::'.$case['name'];
    echo '::error file='.$path.',line='.$line.',title='.$escape($title).'::'.$escape(mb_substr($text, 0, 1800))."\n";

    if (++$count >= 25) {
        break;
    }
}

echo "Muvaffaqiyatsiz testlar: {$count}\n";
