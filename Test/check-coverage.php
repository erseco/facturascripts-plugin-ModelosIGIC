<?php

// Fail closed when the coverage report is absent, empty, or below the required minimum.
// Usage: php check-coverage.php coverage.xml [minimum-percent]
// Phase 1 baseline: 0.5 %. Raise to 90 % once the functional test suite is in place.
$minimum = (float) ($argv[2] ?? 0.5);
$report = simplexml_load_file($argv[1] ?? 'coverage.xml');
if ($report === false || !isset($report->project->metrics)) {
    fwrite(STDERR, "Missing or invalid Clover report.\n");
    exit(1);
}
$metrics = $report->project->metrics;
$total = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
printf(
    "PHP line coverage: %.2f%% (%d/%d); required: %.2f%%\n",
    $total ? 100 * $covered / $total : 0,
    $covered,
    $total,
    $minimum
);
exit($total > 0 && $covered * 100 >= $total * $minimum ? 0 : 1);
