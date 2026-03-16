<?php
declare(strict_types=1);

class AiEvaluationSetService
{
    private const LEVEL_RANK = [
        'none' => 0,
        'low' => 1,
        'medium' => 2,
        'high' => 3,
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function getDefaultCaseFilePath(): string
    {
        return dirname(__DIR__, 2) . '/data/ai_evaluation_cases.php';
    }

    public function loadCaseSet(?string $path = null): array
    {
        $path = trim((string)$path);
        if ($path === '') {
            $path = $this->getDefaultCaseFilePath();
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Evaluatiecases-bestand niet gevonden of niet leesbaar: ' . $path);
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new RuntimeException('Evaluatiecases-bestand moet een array teruggeven.');
        }

        $rawCases = is_array($data['cases'] ?? null) ? $data['cases'] : [];
        $cases = [];
        foreach ($rawCases as $index => $case) {
            if (!is_array($case)) {
                continue;
            }

            $caseId = trim((string)($case['case_id'] ?? ''));
            if ($caseId === '') {
                $caseId = 'case_' . ($index + 1);
            }

            $label = trim((string)($case['label'] ?? ''));
            $videoId = trim((string)($case['video_id'] ?? ''));
            $bucket = trim((string)($case['bucket'] ?? 'uncategorized'));

            $cases[] = [
                'case_id' => $caseId,
                'label' => $label !== '' ? $label : $caseId,
                'bucket' => $bucket !== '' ? $bucket : 'uncategorized',
                'video_id' => $videoId,
                'enabled' => array_key_exists('enabled', $case) ? (bool)$case['enabled'] : ($videoId !== ''),
                'run_frame_download' => !empty($case['run_frame_download']),
                'notes' => trim((string)($case['notes'] ?? '')),
                'expect' => is_array($case['expect'] ?? null) ? $case['expect'] : [],
            ];
        }

        return [
            'version' => trim((string)($data['version'] ?? '1')),
            'updated_at' => trim((string)($data['updated_at'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'path' => $path,
            'cases' => $cases,
            'enabled_case_count' => count(array_filter($cases, static fn(array $case): bool => !empty($case['enabled']))),
            'disabled_case_count' => count(array_filter($cases, static fn(array $case): bool => empty($case['enabled']))),
        ];
    }

    public function filterCaseSet(array $caseSet, array $caseIds = [], array $buckets = []): array
    {
        $wantedCaseIds = array_values(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            $caseIds
        ), static fn(string $value): bool => $value !== ''));
        $wantedBuckets = array_values(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            $buckets
        ), static fn(string $value): bool => $value !== ''));

        if ($wantedCaseIds === [] && $wantedBuckets === []) {
            return $caseSet;
        }

        $caseLookup = array_fill_keys($wantedCaseIds, true);
        $bucketLookup = array_fill_keys($wantedBuckets, true);
        $filteredCases = array_values(array_filter(
            is_array($caseSet['cases'] ?? null) ? $caseSet['cases'] : [],
            static function (array $case) use ($caseLookup, $bucketLookup): bool {
                if ($caseLookup !== [] && isset($caseLookup[(string)($case['case_id'] ?? '')])) {
                    return true;
                }

                if ($bucketLookup !== [] && isset($bucketLookup[(string)($case['bucket'] ?? '')])) {
                    return true;
                }

                return $caseLookup === [] && $bucketLookup === [];
            }
        ));

        $caseSet['cases'] = $filteredCases;
        $caseSet['enabled_case_count'] = count(array_filter($filteredCases, static fn(array $case): bool => !empty($case['enabled'])));
        $caseSet['disabled_case_count'] = count(array_filter($filteredCases, static fn(array $case): bool => empty($case['enabled'])));

        return $caseSet;
    }

    public function evaluateDirectCases(
        array $caseSet,
        AiRetrievalService $retrievalService,
        AiWorkflowService $workflowService,
        array $settings,
        bool $withFrames = false,
        ?VideoFrameExtractor $frameExtractor = null
    ): array {
        $results = [];
        foreach (is_array($caseSet['cases'] ?? null) ? $caseSet['cases'] : [] as $case) {
            if (!is_array($case)) {
                continue;
            }

            $results[] = $this->evaluateDirectCase($case, $retrievalService, $workflowService, $settings, $withFrames, $frameExtractor);
        }

        return $results;
    }

    public function evaluateDirectCase(
        array $case,
        AiRetrievalService $retrievalService,
        AiWorkflowService $workflowService,
        array $settings,
        bool $withFrames = false,
        ?VideoFrameExtractor $frameExtractor = null
    ): array {
        $case = $this->normalizeCase($case);
        if (!$case['enabled']) {
            return $this->buildSkippedCaseResult($case, 'Case is uitgeschakeld.');
        }

        if ($case['video_id'] === '') {
            return $this->buildSkippedCaseResult($case, 'Geen video_id ingevuld.');
        }

        $retrieval = $retrievalService->fetchDirectVideo($case['video_id'], $settings);
        $observed = [
            'retrieval_ok' => (bool)($retrieval['ok'] ?? false),
            'availability_mode' => 'not_checked',
            'downloadable_via_ytdlp' => null,
            'auth_required' => false,
            'technical_selectable' => false,
            'technical_recommended' => false,
            'status' => '',
            'error_code' => '',
            'duration_seconds' => 0,
            'chapter_count' => 0,
            'transcript_source' => 'none',
            'metadata_only' => true,
            'source_evidence_sufficient' => false,
            'source_evidence_level' => 'none',
            'source_evidence_score' => 0.0,
            'frame_download_ok' => null,
            'frame_attempt_count' => 0,
        ];

        if (!($retrieval['ok'] ?? false)) {
            $observed['error_code'] = trim((string)($retrieval['code'] ?? $retrieval['error_code'] ?? 'retrieval_failed'));
            $observed['status'] = $observed['error_code'];
            $graded = $this->gradeCase($case, $observed);

            return [
                'case' => $case,
                'status' => $graded['status'],
                'observed' => $observed,
                'expectation_results' => $graded['expectation_results'],
                'mismatches' => $graded['mismatches'],
                'error' => trim((string)($retrieval['error'] ?? 'Video ophalen is mislukt.')),
            ];
        }

        $source = is_array($retrieval['source'] ?? null) ? $retrieval['source'] : [];
        $technical = $workflowService->assessTechnicalViability($source);
        $sourceEvidence = $workflowService->assessSourceEvidence($source, $settings);
        $preflight = is_array($source['technical_preflight'] ?? null) ? $source['technical_preflight'] : [];

        $observed = [
            'retrieval_ok' => true,
            'availability_mode' => $this->determineAvailabilityMode($preflight, $technical),
            'downloadable_via_ytdlp' => $technical['downloadable_via_ytdlp'] ?? null,
            'auth_required' => !empty($technical['auth_required']),
            'technical_selectable' => !empty($technical['is_selectable']),
            'technical_recommended' => !empty($technical['is_recommended']),
            'status' => trim((string)($technical['status'] ?? '')),
            'error_code' => trim((string)($technical['error_code'] ?? '')),
            'duration_seconds' => max(0, (int)($technical['duration_seconds'] ?? $source['duration_seconds'] ?? 0)),
            'chapter_count' => max(0, (int)($technical['chapter_count'] ?? 0)),
            'transcript_source' => trim((string)($technical['transcript_source'] ?? 'none')),
            'metadata_only' => !empty($technical['metadata_only']),
            'source_evidence_sufficient' => !empty($sourceEvidence['is_sufficient']),
            'source_evidence_level' => trim((string)($sourceEvidence['level'] ?? 'none')),
            'source_evidence_score' => round((float)($sourceEvidence['score'] ?? 0.0), 2),
            'frame_download_ok' => null,
            'frame_attempt_count' => 0,
        ];

        if ($withFrames && $case['run_frame_download'] && $frameExtractor !== null) {
            $cookiesPath = trim((string)($settings['ai_ytdlp_cookies_path'] ?? ''));
            $frameResult = $frameExtractor->extractFrames(
                $case['video_id'],
                max(0, (int)($source['duration_seconds'] ?? 0)),
                is_array($source['chapters'] ?? null) ? $source['chapters'] : [],
                4,
                $cookiesPath !== '' ? $cookiesPath : null
            );
            $observed['frame_download_ok'] = (bool)($frameResult['ok'] ?? false);
            $observed['frame_attempt_count'] = count(is_array($frameResult['download_attempts'] ?? null) ? $frameResult['download_attempts'] : []);
            $observed['frame_error'] = trim((string)($frameResult['error'] ?? ''));
        }

        $graded = $this->gradeCase($case, $observed);

        return [
            'case' => $case,
            'status' => $graded['status'],
            'observed' => $observed,
            'expectation_results' => $graded['expectation_results'],
            'mismatches' => $graded['mismatches'],
            'error' => '',
        ];
    }

    public function gradeCase(array $case, array $observed): array
    {
        $case = $this->normalizeCase($case);
        $expectations = is_array($case['expect'] ?? null) ? $case['expect'] : [];
        $expectationResults = $this->compareExpectations($expectations, $observed);
        $mismatches = array_values(array_map(
            static fn(array $result): string => (string)$result['message'],
            array_filter($expectationResults, static fn(array $result): bool => !$result['ok'])
        ));

        return [
            'status' => empty($mismatches) ? 'pass' : 'fail',
            'expectation_results' => $expectationResults,
            'mismatches' => $mismatches,
        ];
    }

    public function buildSummary(array $results): array
    {
        $summary = [
            'case_count' => count($results),
            'pass_count' => 0,
            'fail_count' => 0,
            'skipped_count' => 0,
            'by_bucket' => [],
            'failing_case_ids' => [],
        ];

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $status = trim((string)($result['status'] ?? ''));
            $case = is_array($result['case'] ?? null) ? $result['case'] : [];
            $bucket = trim((string)($case['bucket'] ?? 'uncategorized'));
            $caseId = trim((string)($case['case_id'] ?? ''));

            if (!isset($summary['by_bucket'][$bucket])) {
                $summary['by_bucket'][$bucket] = [
                    'pass' => 0,
                    'fail' => 0,
                    'skipped' => 0,
                ];
            }

            if ($status === 'pass') {
                $summary['pass_count']++;
                $summary['by_bucket'][$bucket]['pass']++;
            } elseif ($status === 'skipped') {
                $summary['skipped_count']++;
                $summary['by_bucket'][$bucket]['skipped']++;
            } else {
                $summary['fail_count']++;
                $summary['by_bucket'][$bucket]['fail']++;
                if ($caseId !== '') {
                    $summary['failing_case_ids'][] = $caseId;
                }
            }
        }

        ksort($summary['by_bucket']);

        return $summary;
    }

    public function renderCliReport(array $caseSet, array $results, array $summary, bool $withFrames = false): string
    {
        $lines = [];
        $lines[] = 'AI evaluatieset';
        $lines[] = 'Bestand: ' . (string)($caseSet['path'] ?? '');
        $lines[] = 'Cases: ' . (int)($caseSet['enabled_case_count'] ?? 0) . ' actief, ' . (int)($caseSet['disabled_case_count'] ?? 0) . ' uitgeschakeld';
        $lines[] = 'Frame-downloads: ' . ($withFrames ? 'ja' : 'nee');
        $lines[] = '';

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $case = is_array($result['case'] ?? null) ? $result['case'] : [];
            $observed = is_array($result['observed'] ?? null) ? $result['observed'] : [];
            $prefix = match ((string)($result['status'] ?? 'fail')) {
                'pass' => '[PASS]',
                'skipped' => '[SKIP]',
                default => '[FAIL]',
            };

            $lines[] = sprintf(
                '%s %s (%s) video=%s',
                $prefix,
                (string)($case['case_id'] ?? 'case'),
                (string)($case['bucket'] ?? 'uncategorized'),
                (string)($case['video_id'] ?? '')
            );
            $lines[] = '  ' . (string)($case['label'] ?? '');
            if (($case['notes'] ?? '') !== '') {
                $lines[] = '  Notes: ' . (string)$case['notes'];
            }
            if (($result['status'] ?? '') !== 'skipped') {
                $lines[] = sprintf(
                    '  Observed: availability=%s, selectable=%s, recommended=%s, duration=%ss, transcript=%s, evidence=%s (%.2f)',
                    (string)($observed['availability_mode'] ?? 'unknown'),
                    !empty($observed['technical_selectable']) ? 'yes' : 'no',
                    !empty($observed['technical_recommended']) ? 'yes' : 'no',
                    (string)($observed['duration_seconds'] ?? 0),
                    (string)($observed['transcript_source'] ?? 'none'),
                    (string)($observed['source_evidence_level'] ?? 'none'),
                    (float)($observed['source_evidence_score'] ?? 0.0)
                );
                if (array_key_exists('frame_download_ok', $observed) && $observed['frame_download_ok'] !== null) {
                    $lines[] = sprintf(
                        '  Frames: %s (%d attempts)',
                        !empty($observed['frame_download_ok']) ? 'ok' : 'failed',
                        (int)($observed['frame_attempt_count'] ?? 0)
                    );
                }
            }

            $mismatches = is_array($result['mismatches'] ?? null) ? $result['mismatches'] : [];
            if (!empty($mismatches)) {
                foreach ($mismatches as $mismatch) {
                    $lines[] = '  Mismatch: ' . (string)$mismatch;
                }
            }

            $error = trim((string)($result['error'] ?? ''));
            if ($error !== '') {
                $lines[] = '  Error: ' . $error;
            }

            $lines[] = '';
        }

        $lines[] = sprintf(
            'Samenvatting: %d pass, %d fail, %d skip',
            (int)($summary['pass_count'] ?? 0),
            (int)($summary['fail_count'] ?? 0),
            (int)($summary['skipped_count'] ?? 0)
        );
        foreach (is_array($summary['by_bucket'] ?? null) ? $summary['by_bucket'] : [] as $bucket => $counts) {
            $lines[] = sprintf(
                '- %s: %d pass, %d fail, %d skip',
                (string)$bucket,
                (int)($counts['pass'] ?? 0),
                (int)($counts['fail'] ?? 0),
                (int)($counts['skipped'] ?? 0)
            );
        }

        $failingCaseIds = is_array($summary['failing_case_ids'] ?? null) ? $summary['failing_case_ids'] : [];
        if (!empty($failingCaseIds)) {
            $lines[] = 'Failing cases: ' . implode(', ', $failingCaseIds);
        }

        return implode(PHP_EOL, $lines);
    }

    private function normalizeCase(array $case): array
    {
        return [
            'case_id' => trim((string)($case['case_id'] ?? '')),
            'label' => trim((string)($case['label'] ?? '')),
            'bucket' => trim((string)($case['bucket'] ?? 'uncategorized')),
            'video_id' => trim((string)($case['video_id'] ?? '')),
            'enabled' => !array_key_exists('enabled', $case) || (bool)$case['enabled'],
            'run_frame_download' => !empty($case['run_frame_download']),
            'notes' => trim((string)($case['notes'] ?? '')),
            'expect' => is_array($case['expect'] ?? null) ? $case['expect'] : [],
        ];
    }

    private function buildSkippedCaseResult(array $case, string $reason): array
    {
        return [
            'case' => $case,
            'status' => 'skipped',
            'observed' => [],
            'expectation_results' => [],
            'mismatches' => [],
            'error' => $reason,
        ];
    }

    private function determineAvailabilityMode(array $preflight, array $technical): string
    {
        $downloadable = $preflight['downloadable_via_ytdlp'] ?? ($technical['downloadable_via_ytdlp'] ?? null);
        if ($downloadable === true) {
            return !empty($preflight['used_cookies']) || !empty($technical['used_cookies'])
                ? 'cookie_recovered'
                : 'anonymous_ok';
        }

        if (!empty($preflight['auth_required']) || !empty($technical['auth_required'])) {
            return (($preflight['status'] ?? $technical['status'] ?? '') === 'cookies_invalid')
                ? 'cookies_invalid'
                : 'auth_required';
        }

        $status = trim((string)($preflight['status'] ?? $technical['status'] ?? ''));
        return $status !== '' ? $status : 'unknown';
    }

    private function compareExpectations(array $expectations, array $observed): array
    {
        $results = [];

        foreach ($expectations as $key => $expected) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }

            $actual = $observed[$this->mapExpectationKeyToObservedKey($key)] ?? null;
            $ok = true;
            $message = '';

            switch ($key) {
                case 'downloadable_via_ytdlp':
                case 'auth_required':
                case 'technical_selectable':
                case 'technical_recommended':
                case 'metadata_only':
                case 'source_evidence_sufficient':
                case 'frame_download_ok':
                    $ok = $actual === (bool)$expected;
                    $message = sprintf('%s verwacht %s maar was %s', $key, $this->stringifyValue((bool)$expected), $this->stringifyValue($actual));
                    break;

                case 'transcript_source':
                case 'availability_mode':
                    $ok = trim((string)$actual) === trim((string)$expected);
                    $message = sprintf('%s verwacht %s maar was %s', $key, $this->stringifyValue($expected), $this->stringifyValue($actual));
                    break;

                case 'transcript_source_in':
                case 'availability_mode_in':
                case 'status_in':
                case 'error_code_in':
                    $allowed = array_values(array_filter(array_map(
                        static fn($value): string => trim((string)$value),
                        is_array($expected) ? $expected : []
                    ), static fn(string $value): bool => $value !== ''));
                    $ok = in_array(trim((string)$actual), $allowed, true);
                    $message = sprintf('%s verwacht een van [%s] maar was %s', $key, implode(', ', $allowed), $this->stringifyValue($actual));
                    break;

                case 'error_code_not_in':
                    $blocked = array_values(array_filter(array_map(
                        static fn($value): string => trim((string)$value),
                        is_array($expected) ? $expected : []
                    ), static fn(string $value): bool => $value !== ''));
                    $ok = !in_array(trim((string)$actual), $blocked, true);
                    $message = sprintf('%s mocht niet in [%s] vallen maar was %s', $key, implode(', ', $blocked), $this->stringifyValue($actual));
                    break;

                case 'duration_max_seconds':
                case 'chapter_count_max':
                    $ok = (int)$actual <= (int)$expected;
                    $message = sprintf('%s verwacht <= %d maar was %s', $key, (int)$expected, $this->stringifyValue($actual));
                    break;

                case 'duration_min_seconds':
                case 'chapter_count_min':
                case 'frame_attempt_count_min':
                    $ok = (int)$actual >= (int)$expected;
                    $message = sprintf('%s verwacht >= %d maar was %s', $key, (int)$expected, $this->stringifyValue($actual));
                    break;

                case 'source_evidence_min_level':
                    $actualRank = self::LEVEL_RANK[trim((string)$actual)] ?? -1;
                    $expectedRank = self::LEVEL_RANK[trim((string)$expected)] ?? -1;
                    $ok = $actualRank >= $expectedRank;
                    $message = sprintf('%s verwacht minimaal %s maar was %s', $key, $this->stringifyValue($expected), $this->stringifyValue($actual));
                    break;

                default:
                    continue 2;
            }

            $results[] = [
                'key' => $key,
                'expected' => $expected,
                'actual' => $actual,
                'ok' => $ok,
                'message' => $message,
            ];
        }

        return $results;
    }

    private function mapExpectationKeyToObservedKey(string $key): string
    {
        return match ($key) {
            'transcript_source_in' => 'transcript_source',
            'availability_mode_in' => 'availability_mode',
            'status_in' => 'status',
            'error_code_in', 'error_code_not_in' => 'error_code',
            'duration_max_seconds', 'duration_min_seconds' => 'duration_seconds',
            'chapter_count_max', 'chapter_count_min' => 'chapter_count',
            'source_evidence_min_level' => 'source_evidence_level',
            'frame_attempt_count_min' => 'frame_attempt_count',
            default => $key,
        };
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string)$value;
    }
}
