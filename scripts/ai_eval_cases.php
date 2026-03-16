<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/Session.php';

spl_autoload_register(function (string $class): void {
    $class = str_replace('\\', '/', $class);
    $base = __DIR__ . '/../src/';

    $paths = [
        $base . $class . '.php',
        $base . 'models/' . $class . '.php',
        $base . 'controllers/' . $class . '.php',
        $base . 'services/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

function parseAiEvalArgs(array $argv): array
{
    $options = [
        'case_file' => '',
        'case_ids' => [],
        'buckets' => [],
        'with_frames' => false,
        'json' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--with-frames') {
            $options['with_frames'] = true;
            continue;
        }
        if ($arg === '--json') {
            $options['json'] = true;
            continue;
        }
        if (str_starts_with($arg, '--case-file=')) {
            $options['case_file'] = trim(substr($arg, strlen('--case-file=')));
            continue;
        }
        if (str_starts_with($arg, '--case=')) {
            $options['case_ids'][] = trim(substr($arg, strlen('--case=')));
            continue;
        }
        if (str_starts_with($arg, '--bucket=')) {
            $options['buckets'][] = trim(substr($arg, strlen('--bucket=')));
            continue;
        }
        if (in_array($arg, ['-h', '--help'], true)) {
            echo "Gebruik: php scripts/ai_eval_cases.php [--case-file=/pad/naar/cases.php] [--case=case_id] [--bucket=bucket] [--with-frames] [--json]\n";
            exit(0);
        }

        fwrite(STDERR, "Onbekende optie: {$arg}\n");
        exit(1);
    }

    return $options;
}

function createAiEvalPdo(): PDO
{
    $databasePath = __DIR__ . '/../data/database.sqlite';
    if (!is_file($databasePath)) {
        throw new RuntimeException('Database niet gevonden: ' . $databasePath);
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

try {
    $options = parseAiEvalArgs($argv);
    $pdo = createAiEvalPdo();

    $settingsModel = new AppSetting($pdo);
    $usageService = new AiUsageService($pdo);
    $accessService = new AiAccessService($pdo, $settingsModel, $usageService);
    $settings = $accessService->getSettingsWithDefaults();

    $evaluationService = new AiEvaluationSetService($pdo);
    $caseSet = $evaluationService->loadCaseSet($options['case_file']);
    $caseSet = $evaluationService->filterCaseSet($caseSet, $options['case_ids'], $options['buckets']);

    $retrievalService = new AiRetrievalService($pdo);
    $workflowService = new AiWorkflowService($pdo, $retrievalService);
    $frameExtractor = $options['with_frames'] ? new VideoFrameExtractor() : null;

    $results = $evaluationService->evaluateDirectCases(
        $caseSet,
        $retrievalService,
        $workflowService,
        $settings,
        $options['with_frames'],
        $frameExtractor
    );
    $summary = $evaluationService->buildSummary($results);

    if ($options['json']) {
        echo json_encode([
            'case_set' => $caseSet,
            'results' => $results,
            'summary' => $summary,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo $evaluationService->renderCliReport($caseSet, $results, $summary, $options['with_frames']) . PHP_EOL;
    }

    exit(((int)($summary['fail_count'] ?? 0)) > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, '[AI eval] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
