<?php

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/k6-profile.php <source-profile.json> <output-profile.json>\n");
    exit(1);
}

[$script, $source, $output] = $argv;

try {
    $profile = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, "Unable to read k6 profile {$source}: {$error->getMessage()}\n");
    exit(1);
}

if (! is_array($profile)) {
    fwrite(STDERR, "k6 profile {$source} must contain a JSON object.\n");
    exit(1);
}

$env = static fn(string $name): ?string => getenv($name) === false ? null : (string) getenv($name);
$intEnv = static function (string $name) use ($env): ?int {
    $value = $env($name);
    if ($value === null || $value === '') {
        return null;
    }

    return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : null;
};
$profileEnv = $profile['xEnv'] ?? [];
unset($profile['xEnv']);

$scenarioName = $env('K6_SCENARIO_NAME') ?: 'student_journey';
$profile['scenarios'] ??= [];
$profile['scenarios'][$scenarioName] ??= ['executor' => 'ramping-vus'];

$scenario = $profile['scenarios'][$scenarioName];
$executor = $env('K6_SCENARIO') ?: ($scenario['executor'] ?? 'ramping-vus');
$scenario['executor'] = $executor;
$scenario['exec'] ??= 'default';
$scenario['tags']['workload'] ??= 'student_journey';

$vus = $intEnv('K6_VUS');
$rampUp = $env('K6_RAMP_UP');
$hold = $env('K6_HOLD');
$rampDown = $env('K6_RAMP_DOWN');

switch ($executor) {
    case 'per-vu-iterations':
        if ($vus !== null) {
            $scenario['vus'] = $vus;
        }
        if (($iterations = $intEnv('K6_ITERATIONS_PER_VU')) !== null) {
            $scenario['iterations'] = $iterations;
        }
        if (($maxDuration = $env('K6_MAX_DURATION')) !== null && $maxDuration !== '') {
            $scenario['maxDuration'] = $maxDuration;
        }
        break;

    case 'constant-vus':
        if ($vus !== null) {
            $scenario['vus'] = $vus;
        }
        if ($hold !== null && $hold !== '') {
            $scenario['duration'] = $hold;
        }
        break;

    case 'ramping-vus':
        $currentStages = $scenario['stages'] ?? [];
        $target = $vus
            ?? ($currentStages[1]['target'] ?? $currentStages[0]['target'] ?? 20);

        $scenario['startVUs'] = $scenario['startVUs'] ?? 0;
        $scenario['stages'] = [
            [
                'target' => $target,
                'duration' => $rampUp ?: ($currentStages[0]['duration'] ?? '1m'),
            ],
            [
                'target' => $target,
                'duration' => $hold ?: ($currentStages[1]['duration'] ?? '20m'),
            ],
            [
                'target' => 0,
                'duration' => $rampDown ?: ($currentStages[2]['duration'] ?? '1m'),
            ],
        ];

        if (($gracefulRampDown = $env('K6_GRACEFUL_RAMP_DOWN')) !== null && $gracefulRampDown !== '') {
            $scenario['gracefulRampDown'] = $gracefulRampDown;
        }
        break;

    case 'ramping-arrival-rate':
        if (($startRate = $intEnv('K6_START_RATE')) !== null) {
            $scenario['startRate'] = $startRate;
        }
        if (($timeUnit = $env('K6_RATE_TIME_UNIT')) !== null && $timeUnit !== '') {
            $scenario['timeUnit'] = $timeUnit;
        }
        if (($preAllocatedVus = $intEnv('K6_PRE_ALLOCATED_VUS')) !== null) {
            $scenario['preAllocatedVUs'] = $preAllocatedVus;
        }
        if (($maxVus = $intEnv('K6_MAX_VUS')) !== null) {
            $scenario['maxVUs'] = $maxVus;
        }
        if (($targets = $env('K6_RATE_TARGETS')) !== null && $targets !== '') {
            $duration = $env('K6_STAGE_DURATION') ?: '1m';
            $scenario['stages'] = array_map(
                static fn(int $target): array => [
                    'target' => $target,
                    'duration' => $duration,
                ],
                array_values(array_filter(
                    array_map(
                        static fn(string $target): ?int => filter_var(trim($target), FILTER_VALIDATE_INT) !== false
                            ? (int) trim($target)
                            : null,
                        explode(',', $targets),
                    ),
                    static fn(?int $target): bool => $target !== null,
                )),
            );
        }
        if (($gracefulStop = $env('K6_GRACEFUL_STOP')) !== null && $gracefulStop !== '') {
            $scenario['gracefulStop'] = $gracefulStop;
        }
        break;
}

$profile['scenarios'][$scenarioName] = $scenario;

if (! is_dir(dirname($output)) && ! mkdir(dirname($output), 0777, true) && ! is_dir(dirname($output))) {
    fwrite(STDERR, "Unable to create k6 profile output directory: " . dirname($output) . "\n");
    exit(1);
}

file_put_contents(
    $output,
    json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
);

$envOutput = "{$output}.env";
$envLines = [];
if (is_array($profileEnv)) {
    foreach ($profileEnv as $key => $value) {
        if (is_string($key) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            $envLines[] = "{$key}={$value}";
        }
    }
}

file_put_contents($envOutput, implode(PHP_EOL, $envLines) . ($envLines === [] ? '' : PHP_EOL));

