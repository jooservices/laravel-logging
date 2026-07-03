<?php

declare(strict_types=1);

$minimum = (float) ($argv[1] ?? 90);
$file = $argv[2] ?? 'coverage.xml';

if (! is_file($file)) {
    fwrite(STDERR, "Coverage file not found: {$file}\n");

    exit(1);
}

$xml = simplexml_load_file($file);

if ($xml === false) {
    fwrite(STDERR, "Unable to parse coverage file: {$file}\n");

    exit(1);
}

$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    fwrite(STDERR, "Coverage metrics missing in: {$file}\n");

    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percentage = $statements > 0 ? ($covered / $statements) * 100 : 100.0;

printf("Line coverage: %.2f%% (%d/%d)\n", $percentage, $covered, $statements);

if ($percentage + 0.0001 < $minimum) {
    fwrite(STDERR, sprintf("Coverage %.2f%% is below the %.2f%% threshold.\n", $percentage, $minimum));

    exit(1);
}

exit(0);
