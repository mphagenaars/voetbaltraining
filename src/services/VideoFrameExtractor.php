<?php
declare(strict_types=1);

/**
 * Extracts keyframes from YouTube videos using yt-dlp and ffmpeg.
 *
 * Returns an array of frames with timestamps and base64-encoded JPEG data,
 * ready for use with OpenRouterClient::buildImageContent().
 */
class VideoFrameExtractor {
    private string $tmpDir;
    private string $ytDlpPath;
    private string $ffmpegPath;

    /** Maximum video length we'll attempt to process (15 minutes) */
    private const MAX_DURATION_SECONDS = 900;

    /** Target number of frames to extract */
    private const DEFAULT_FRAME_COUNT = 10;

    /** JPEG quality for extracted frames (1-31 for ffmpeg, lower = better) */
    private const JPEG_QUALITY = 3;

    /** Max width for extracted frames (keeps vision costs down, 720p is sufficient) */
    private const FRAME_MAX_WIDTH = 1280;

    /** Download timeout in seconds */
    private const DOWNLOAD_TIMEOUT = 120;

    /** Lightweight yt-dlp availability probe timeout in seconds */
    private const PREFLIGHT_TIMEOUT = 45;

    public function __construct(?string $tmpDir = null) {
        $this->tmpDir = $tmpDir ?? dirname(__DIR__, 2) . '/data/tmp/frames';
        $this->ytDlpPath = $this->findExecutable('yt-dlp');
        $this->ffmpegPath = $this->findExecutable('ffmpeg');
    }

    /**
     * Extract keyframes from a YouTube video.
     *
     * @param string $videoId     YouTube video ID
     * @param int    $duration    Video duration in seconds (0 = unknown)
     * @param array  $chapters    Optional chapter data [['start_seconds' => int, 'title' => string], ...]
     * @param int    $frameCount  Target number of frames (8-12)
     * @return array{ok: bool, frames?: array, error?: string, download_attempts?: array}
     */
    public function extractFrames(
        string $videoId,
        int $duration = 0,
        array $chapters = [],
        int $frameCount = self::DEFAULT_FRAME_COUNT,
        ?string $cookiesPath = null
    ): array {
        if ($this->ytDlpPath === '' || $this->ffmpegPath === '') {
            return ['ok' => false, 'error' => 'yt-dlp of ffmpeg niet gevonden op het systeem.'];
        }

        if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
            return ['ok' => false, 'error' => 'Ongeldig YouTube video ID.'];
        }

        if ($duration > self::MAX_DURATION_SECONDS) {
            return ['ok' => false, 'error' => 'Video is te lang voor frame-extractie (max ' . (self::MAX_DURATION_SECONDS / 60) . ' minuten).'];
        }

        $frameCount = max(4, min(16, $frameCount));

        $workDir = $this->prepareWorkDir($videoId);
        if ($workDir === null) {
            return ['ok' => false, 'error' => 'Kon tijdelijke map niet aanmaken.'];
        }

        try {
            // Step 1: Download video at low quality
            $downloadResult = $this->downloadVideo($videoId, $workDir, $cookiesPath);
            if (!$downloadResult['ok']) {
                return [
                    'ok' => false,
                    'error' => $downloadResult['error'],
                    'download_attempts' => is_array($downloadResult['attempts'] ?? null) ? $downloadResult['attempts'] : [],
                ];
            }
            $videoPath = $downloadResult['path'];

            // Step 2: Determine actual duration if not provided
            if ($duration <= 0) {
                $duration = $this->probeDuration($videoPath);
            }
            if ($duration <= 0) {
                return [
                    'ok' => false,
                    'error' => 'Kon videoduur niet bepalen.',
                    'download_attempts' => is_array($downloadResult['attempts'] ?? null) ? $downloadResult['attempts'] : [],
                ];
            }

            // Step 3: Calculate timestamps for frame extraction
            $timestamps = $this->calculateTimestamps($duration, $chapters, $frameCount);

            // Step 4: Extract frames at calculated timestamps
            $frames = $this->extractFramesAtTimestamps($videoPath, $timestamps, $workDir);

            if (empty($frames)) {
                return [
                    'ok' => false,
                    'error' => 'Geen frames konden worden geëxtraheerd.',
                    'download_attempts' => is_array($downloadResult['attempts'] ?? null) ? $downloadResult['attempts'] : [],
                ];
            }

            return [
                'ok' => true,
                'frames' => $frames,
                'duration' => $duration,
                'download_attempts' => is_array($downloadResult['attempts'] ?? null) ? $downloadResult['attempts'] : [],
            ];
        } finally {
            $this->cleanupWorkDir($workDir);
        }
    }

    /**
     * Run a lightweight yt-dlp probe without downloading the full video.
     *
     * @return array{
     *   checked: bool,
     *   downloadable_via_ytdlp: ?bool,
     *   auth_required: bool,
     *   error_code: ?string,
     *   error: string,
     *   duration_seconds: int,
     *   used_cookies: bool,
     *   attempts: array
     * }
     */
    public function probeAvailability(string $videoId, ?string $cookiesPath = null): array {
        if ($this->ytDlpPath === '') {
            return [
                'checked' => false,
                'downloadable_via_ytdlp' => null,
                'auth_required' => false,
                'error_code' => 'missing_ytdlp',
                'error' => 'yt-dlp niet gevonden op het systeem.',
                'duration_seconds' => 0,
                'used_cookies' => false,
                'attempts' => [],
            ];
        }

        if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
            return [
                'checked' => false,
                'downloadable_via_ytdlp' => false,
                'auth_required' => false,
                'error_code' => 'invalid_video_id',
                'error' => 'Ongeldig YouTube video ID.',
                'duration_seconds' => 0,
                'used_cookies' => false,
                'attempts' => [],
            ];
        }

        $cookiesProvided = trim((string)$cookiesPath) !== '';
        $normalizedCookiesPath = $this->normalizeCookiesPath($cookiesPath);
        $attempts = [];

        $anonymousAttempt = $this->runAvailabilityProbeAttempt($videoId, null);
        $attempts[] = $this->buildAvailabilityAttemptRecord('probe', 'anonymous', $anonymousAttempt);
        if ($anonymousAttempt['ok']) {
            return [
                'checked' => true,
                'downloadable_via_ytdlp' => true,
                'auth_required' => false,
                'error_code' => null,
                'error' => '',
                'duration_seconds' => max(0, (int)($anonymousAttempt['duration_seconds'] ?? 0)),
                'used_cookies' => false,
                'attempts' => $attempts,
            ];
        }

        $anonymousClassification = $this->classifyAvailabilityError((string)($anonymousAttempt['error'] ?? ''));
        if (!$anonymousClassification['auth_required'] || !$cookiesProvided) {
            return [
                'checked' => true,
                'downloadable_via_ytdlp' => $this->determineProbeDownloadableState($anonymousClassification),
                'auth_required' => $anonymousClassification['auth_required'],
                'error_code' => $anonymousClassification['error_code'],
                'error' => $anonymousClassification['error'],
                'duration_seconds' => 0,
                'used_cookies' => false,
                'attempts' => $attempts,
            ];
        }

        if ($normalizedCookiesPath === null) {
            $attempts[] = $this->buildSkippedAvailabilityAttemptRecord(
                'probe',
                'cookies',
                'cookies_invalid',
                'Video vereist authenticatie, maar het geconfigureerde cookies.txt-bestand is niet leesbaar.',
                true
            );
            return [
                'checked' => true,
                'downloadable_via_ytdlp' => false,
                'auth_required' => true,
                'error_code' => 'cookies_invalid',
                'error' => 'Video vereist authenticatie, maar het geconfigureerde cookies.txt-bestand is niet leesbaar.',
                'duration_seconds' => 0,
                'used_cookies' => false,
                'attempts' => $attempts,
            ];
        }

        $cookieAttempt = $this->runAvailabilityProbeAttempt($videoId, $normalizedCookiesPath);
        $attempts[] = $this->buildAvailabilityAttemptRecord('probe', 'cookies', $cookieAttempt);
        if ($cookieAttempt['ok']) {
            return [
                'checked' => true,
                'downloadable_via_ytdlp' => true,
                'auth_required' => true,
                'error_code' => null,
                'error' => '',
                'duration_seconds' => max(0, (int)($cookieAttempt['duration_seconds'] ?? 0)),
                'used_cookies' => true,
                'attempts' => $attempts,
            ];
        }

        $cookieClassification = $this->classifyAvailabilityError((string)($cookieAttempt['error'] ?? ''));
        return [
            'checked' => true,
            'downloadable_via_ytdlp' => $this->determineProbeDownloadableState($cookieClassification),
            'auth_required' => true,
            'error_code' => $cookieClassification['error_code'],
            'error' => $cookieClassification['error'],
            'duration_seconds' => 0,
            'used_cookies' => true,
            'attempts' => $attempts,
        ];
    }

    /**
     * Calculate frame timestamps, using chapters if available.
     *
     * @return float[] Array of timestamps in seconds
     */
    public function calculateTimestamps(int $duration, array $chapters, int $frameCount): array {
        if ($duration <= 0) {
            return [];
        }

        // Skip first and last 2 seconds (usually intro/outro)
        $start = min(2.0, $duration * 0.02);
        $end = max($start + 1, $duration - 2.0);
        $usableDuration = $end - $start;

        if (!empty($chapters) && count($chapters) >= 2) {
            return $this->timestampsFromChapters($chapters, $duration, $start, $end, $frameCount);
        }

        return $this->timestampsUniform($start, $usableDuration, $frameCount);
    }

    /**
     * Uniform distribution of timestamps across the video.
     */
    private function timestampsUniform(float $start, float $usableDuration, int $frameCount): array {
        $timestamps = [];
        $interval = $usableDuration / max(1, $frameCount - 1);

        for ($i = 0; $i < $frameCount; $i++) {
            $timestamps[] = round($start + ($i * $interval), 2);
        }

        return $timestamps;
    }

    /**
     * Chapter-aware timestamp selection: distribute frames across chapters
     * proportional to chapter length, with at least 1 frame per chapter.
     */
    private function timestampsFromChapters(array $chapters, int $duration, float $start, float $end, int $frameCount): array {
        // Normalize chapters to have start_seconds
        $normalizedChapters = [];
        foreach ($chapters as $ch) {
            $chStart = 0.0;
            if (isset($ch['start_seconds'])) {
                $chStart = (float)$ch['start_seconds'];
            } elseif (isset($ch['start'])) {
                $chStart = (float)$this->parseTimestamp($ch['start']);
            }
            $normalizedChapters[] = ['start' => $chStart, 'title' => (string)($ch['title'] ?? '')];
        }

        // Sort by start time
        usort($normalizedChapters, fn($a, $b) => $a['start'] <=> $b['start']);

        // Calculate chapter durations
        $chapterCount = count($normalizedChapters);
        $chapterDurations = [];
        for ($i = 0; $i < $chapterCount; $i++) {
            $chStart = $normalizedChapters[$i]['start'];
            $chEnd = ($i + 1 < $chapterCount) ? $normalizedChapters[$i + 1]['start'] : (float)$duration;
            $chapterDurations[] = [
                'start' => $chStart,
                'end' => $chEnd,
                'duration' => max(0.0, $chEnd - $chStart),
            ];
        }

        $totalDuration = array_sum(array_column($chapterDurations, 'duration'));
        if ($totalDuration <= 0) {
            return $this->timestampsUniform($start, $end - $start, $frameCount);
        }

        // Allocate frames per chapter: at least 1 per chapter, rest proportional
        $framesPerChapter = [];
        $remaining = $frameCount;

        // First pass: 1 frame per chapter (if we have enough budget)
        $basePerChapter = min(1, intdiv($frameCount, $chapterCount));
        foreach ($chapterDurations as $i => $ch) {
            $framesPerChapter[$i] = $basePerChapter;
            $remaining -= $basePerChapter;
        }

        // Second pass: distribute remaining proportionally
        if ($remaining > 0) {
            $proportions = [];
            foreach ($chapterDurations as $i => $ch) {
                $proportions[$i] = $ch['duration'] / $totalDuration;
            }

            // Sort by proportion descending to distribute extra frames to longer chapters
            arsort($proportions);
            foreach ($proportions as $i => $prop) {
                $extra = max(0, (int)round($prop * $remaining));
                if ($extra > $remaining) {
                    $extra = $remaining;
                }
                $framesPerChapter[$i] += $extra;
                $remaining -= $extra;
                if ($remaining <= 0) break;
            }

            // If still frames left, give to longest chapter
            if ($remaining > 0) {
                $longestIdx = array_key_first($proportions);
                $framesPerChapter[$longestIdx] += $remaining;
            }
        }

        // Generate timestamps within each chapter
        $timestamps = [];
        foreach ($chapterDurations as $i => $ch) {
            $count = $framesPerChapter[$i] ?? 0;
            if ($count <= 0 || $ch['duration'] <= 0) continue;

            $chStart = max($ch['start'], $start);
            $chEnd = min($ch['end'], $end);
            $chUsable = $chEnd - $chStart;

            if ($chUsable <= 0) continue;

            if ($count === 1) {
                // Single frame: place at 30% into the chapter (past intro of that section)
                $timestamps[] = round($chStart + $chUsable * 0.3, 2);
            } else {
                $interval = $chUsable / max(1, $count);
                for ($j = 0; $j < $count; $j++) {
                    $timestamps[] = round($chStart + ($j + 0.5) * $interval, 2);
                }
            }
        }

        sort($timestamps);
        return $timestamps;
    }

    private function downloadVideo(string $videoId, string $workDir, ?string $cookiesPath = null): array {
        $cookiesProvided = trim((string)$cookiesPath) !== '';
        $normalizedCookiesPath = $this->normalizeCookiesPath($cookiesPath);
        $attempts = [];

        $downloadResult = $this->runDownloadAttempt($videoId, $workDir, null);
        $attempts[] = $this->buildAvailabilityAttemptRecord('download', 'anonymous', $downloadResult);
        if ($downloadResult['ok']) {
            $downloadResult['attempts'] = $attempts;
            return $downloadResult;
        }

        $classification = $this->classifyAvailabilityError((string)($downloadResult['error'] ?? ''));
        if (!$classification['auth_required'] || !$cookiesProvided) {
            return [
                'ok' => false,
                'error' => 'Video downloaden mislukt: ' . ($downloadResult['error'] ?? 'onbekende fout'),
                'attempts' => $attempts,
            ];
        }

        if ($normalizedCookiesPath === null) {
            $attempts[] = $this->buildSkippedAvailabilityAttemptRecord(
                'download',
                'cookies',
                'cookies_invalid',
                'Video downloaden mislukt: authenticatie vereist, maar het geconfigureerde cookies.txt-bestand is niet leesbaar.',
                true
            );
            return [
                'ok' => false,
                'error' => 'Video downloaden mislukt: authenticatie vereist, maar het geconfigureerde cookies.txt-bestand is niet leesbaar.',
                'attempts' => $attempts,
            ];
        }

        $cookieResult = $this->runDownloadAttempt($videoId, $workDir, $normalizedCookiesPath);
        $attempts[] = $this->buildAvailabilityAttemptRecord('download', 'cookies', $cookieResult);
        if ($cookieResult['ok']) {
            $cookieResult['attempts'] = $attempts;
            return $cookieResult;
        }

        return [
            'ok' => false,
            'error' => 'Video downloaden mislukt: ' . ($cookieResult['error'] ?? 'onbekende fout'),
            'attempts' => $attempts,
        ];
    }

    private function runDownloadAttempt(string $videoId, string $workDir, ?string $cookiesPath = null): array {
        $outputTemplate = $workDir . '/video.%(ext)s';
        $url = 'https://www.youtube.com/watch?v=' . $videoId;

        $cmd = [
            $this->ytDlpPath,
            '--no-playlist',
            '--no-warnings',
            '--quiet',
            // Download worst video quality that's at least 360p, no audio needed
            '-f', 'worstvideo[height>=360][ext=mp4]/worstvideo[height>=360]/worst[height>=360]/worstvideo/worst',
            '--no-audio',
            '-o', $outputTemplate,
            '--socket-timeout', '30',
            '--retries', '2',
            '--no-check-certificates',
        ];
        if ($cookiesPath !== null) {
            $cmd[] = '--cookies';
            $cmd[] = $cookiesPath;
        }
        $cmd[] = $url;

        $result = $this->runProcess($cmd, self::DOWNLOAD_TIMEOUT);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => (string)($result['error'] ?? 'onbekende fout')];
        }

        // Find the downloaded file
        $files = glob($workDir . '/video.*');
        if (empty($files)) {
            return ['ok' => false, 'error' => 'Gedownloade video niet gevonden.'];
        }

        return ['ok' => true, 'path' => $files[0]];
    }

    private function runAvailabilityProbeAttempt(string $videoId, ?string $cookiesPath = null): array {
        $url = 'https://www.youtube.com/watch?v=' . $videoId;
        $cmd = [
            $this->ytDlpPath,
            '--no-playlist',
            '--no-warnings',
            '--skip-download',
            '--simulate',
            '--dump-single-json',
            '--socket-timeout', '20',
            '--retries', '1',
            '--no-check-certificates',
        ];
        if ($cookiesPath !== null) {
            $cmd[] = '--cookies';
            $cmd[] = $cookiesPath;
        }
        $cmd[] = $url;

        $result = $this->runProcess($cmd, self::PREFLIGHT_TIMEOUT);
        if (!($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => (string)($result['error'] ?? ''),
            ];
        }

        $decoded = json_decode(trim((string)($result['output'] ?? '')), true);
        return [
            'ok' => true,
            'duration_seconds' => is_array($decoded) ? max(0, (int)($decoded['duration'] ?? 0)) : 0,
        ];
    }

    private function probeDuration(string $videoPath): int {
        $cmd = [
            $this->ffmpegPath,
            '-i', $videoPath,
            '-f', 'null',
            '-',
        ];

        // ffprobe would be cleaner, but ffmpeg -i also works
        $ffprobePath = $this->findExecutable('ffprobe');
        if ($ffprobePath !== '') {
            $cmd = [
                $ffprobePath,
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $videoPath,
            ];
            $result = $this->runProcess($cmd, 15);
            if ($result['ok'] && is_numeric(trim($result['output'] ?? ''))) {
                return (int)round((float)trim($result['output']));
            }
        }

        return 0;
    }

    /**
     * Extract frames at specific timestamps using ffmpeg.
     *
     * @return array<array{timestamp: float, timestamp_formatted: string, base64: string, path: string}>
     */
    private function extractFramesAtTimestamps(string $videoPath, array $timestamps, string $workDir): array {
        $frames = [];

        foreach ($timestamps as $i => $ts) {
            $outputPath = $workDir . '/frame_' . str_pad((string)$i, 3, '0', STR_PAD_LEFT) . '.jpg';

            $cmd = [
                $this->ffmpegPath,
                '-ss', (string)$ts,
                '-i', $videoPath,
                '-vframes', '1',
                '-vf', 'scale=' . self::FRAME_MAX_WIDTH . ':-2',
                '-q:v', (string)self::JPEG_QUALITY,
                '-y',
                $outputPath,
            ];

            $result = $this->runProcess($cmd, 15);
            if (!$result['ok'] || !file_exists($outputPath) || filesize($outputPath) < 100) {
                continue;
            }

            $imageData = file_get_contents($outputPath);
            if ($imageData === false) {
                continue;
            }

            $base64 = base64_encode($imageData);
            $frames[] = [
                'timestamp' => $ts,
                'timestamp_formatted' => $this->formatTimestamp($ts),
                'base64' => $base64,
                'data_uri' => 'data:image/jpeg;base64,' . $base64,
            ];
        }

        return $frames;
    }

    private function prepareWorkDir(string $videoId): ?string {
        $dir = $this->tmpDir . '/' . $videoId . '_' . bin2hex(random_bytes(4));

        if (!is_dir($this->tmpDir)) {
            if (!mkdir($this->tmpDir, 0755, true) && !is_dir($this->tmpDir)) {
                return null;
            }
        }

        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        return $dir;
    }

    private function cleanupWorkDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        @rmdir($dir);
    }

    /**
     * Clean up all stale working directories older than the given max age.
     */
    public function cleanupStaleWorkDirs(int $maxAgeSeconds = 3600): int {
        if (!is_dir($this->tmpDir)) {
            return 0;
        }

        $cleaned = 0;
        $dirs = glob($this->tmpDir . '/*', GLOB_ONLYDIR);
        if (!is_array($dirs)) {
            return 0;
        }

        $cutoff = time() - $maxAgeSeconds;
        foreach ($dirs as $dir) {
            if (@filemtime($dir) < $cutoff) {
                $this->cleanupWorkDir($dir);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    protected function runProcess(array $cmd, int $timeoutSeconds): array {
        $escapedCmd = implode(' ', array_map('escapeshellarg', $cmd));

        $descriptors = [
            0 => ['pipe', 'r'],       // stdin
            1 => ['pipe', 'w'],       // stdout
            2 => ['pipe', 'w'],       // stderr
        ];

        $process = proc_open($escapedCmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['ok' => false, 'error' => 'Kon proces niet starten.'];
        }

        fclose($pipes[0]); // Close stdin

        // Non-blocking read with timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startTime = time();

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Process finished, drain remaining output
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                break;
            }

            if ((time() - $startTime) > $timeoutSeconds) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return ['ok' => false, 'error' => 'Timeout na ' . $timeoutSeconds . ' seconden.'];
            }

            $stdout .= fread($pipes[1], 8192) ?: '';
            $stderr .= fread($pipes[2], 8192) ?: '';
            usleep(50000); // 50ms
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = $status['exitcode'] ?? proc_close($process);

        if ($exitCode !== 0) {
            $errorMsg = trim($stderr) !== '' ? trim($stderr) : 'Exit code ' . $exitCode;
            // Truncate long error messages
            if (strlen($errorMsg) > 500) {
                $errorMsg = substr($errorMsg, 0, 500) . '...';
            }
            return ['ok' => false, 'error' => $errorMsg, 'output' => $stdout];
        }

        return ['ok' => true, 'output' => $stdout];
    }

    protected function findExecutable(string $name): string {
        $paths = [
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
        ];

        foreach ($this->resolveCandidateHomeDirs() as $homeDir) {
            $paths[] = rtrim($homeDir, '/\\') . '/.local/bin/' . $name;
        }

        $paths = array_values(array_unique(array_filter($paths, static fn($path): bool => is_string($path) && trim($path) !== '')));

        foreach ($paths as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try which
        $result = @shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null');
        if ($result !== null && trim($result) !== '') {
            $path = trim($result);
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * Web-PHP often runs without HOME in $_SERVER or with a stripped PATH.
     * Probe a few likely home directories so ~/.local/bin/yt-dlp remains discoverable.
     *
     * @return string[]
     */
    protected function resolveCandidateHomeDirs(): array {
        $homes = [];

        foreach ([$_SERVER['HOME'] ?? null, getenv('HOME') ?: null] as $home) {
            if (is_string($home) && trim($home) !== '') {
                $homes[] = trim($home);
            }
        }

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $runtimeUser = @posix_getpwuid((int)posix_geteuid());
            if (is_array($runtimeUser) && !empty($runtimeUser['dir']) && is_string($runtimeUser['dir'])) {
                $homes[] = trim($runtimeUser['dir']);
            }

            $projectRoot = dirname(__DIR__, 2);
            $ownerId = @fileowner($projectRoot);
            if (is_int($ownerId) && $ownerId >= 0) {
                $projectOwner = @posix_getpwuid($ownerId);
                if (is_array($projectOwner) && !empty($projectOwner['dir']) && is_string($projectOwner['dir'])) {
                    $homes[] = trim($projectOwner['dir']);
                }
            }
        }

        return array_values(array_unique(array_filter($homes, static fn($home): bool => $home !== '')));
    }

    private function formatTimestamp(float $seconds): string {
        $mins = (int)floor($seconds / 60);
        $secs = (int)floor($seconds) % 60;
        return sprintf('%02d:%02d', $mins, $secs);
    }

    private function parseTimestamp(string $ts): float {
        // Accepts "MM:SS" or "HH:MM:SS" or just seconds
        $parts = explode(':', $ts);
        if (count($parts) === 3) {
            return (float)$parts[0] * 3600 + (float)$parts[1] * 60 + (float)$parts[2];
        }
        if (count($parts) === 2) {
            return (float)$parts[0] * 60 + (float)$parts[1];
        }
        return (float)$ts;
    }

    private function normalizeCookiesPath(?string $cookiesPath): ?string {
        $cookiesPath = trim((string)$cookiesPath);
        if ($cookiesPath === '') {
            return null;
        }

        if (!is_file($cookiesPath) || !is_readable($cookiesPath)) {
            return null;
        }

        return $cookiesPath;
    }

    private function buildAvailabilityAttemptRecord(string $stage, string $mode, array $result): array
    {
        $ok = !empty($result['ok']);
        $classification = $ok
            ? ['error_code' => null, 'auth_required' => false, 'error' => '']
            : $this->classifyAvailabilityError((string)($result['error'] ?? ''));

        return [
            'stage' => $stage,
            'mode' => $mode,
            'attempted' => true,
            'used_cookies' => $mode === 'cookies',
            'ok' => $ok,
            'auth_required' => (bool)($classification['auth_required'] ?? false),
            'error_code' => $classification['error_code'] ?? null,
            'error' => trim((string)($classification['error'] ?? '')),
            'duration_seconds' => max(0, (int)($result['duration_seconds'] ?? 0)),
        ];
    }

    private function buildSkippedAvailabilityAttemptRecord(
        string $stage,
        string $mode,
        string $errorCode,
        string $error,
        bool $authRequired
    ): array {
        return [
            'stage' => $stage,
            'mode' => $mode,
            'attempted' => false,
            'used_cookies' => $mode === 'cookies',
            'ok' => false,
            'auth_required' => $authRequired,
            'error_code' => $errorCode,
            'error' => trim($error),
            'duration_seconds' => 0,
        ];
    }

    /**
     * Map noisy yt-dlp stderr to a smaller error taxonomy for ranking and UI messaging.
     *
     * @return array{error_code: string, auth_required: bool, error: string}
     */
    private function classifyAvailabilityError(string $error): array {
        $error = trim($error);
        $normalized = strtolower($error);

        if ($normalized === '') {
            return [
                'error_code' => 'unknown_error',
                'auth_required' => false,
                'error' => 'yt-dlp availability-check mislukte zonder foutmelding.',
            ];
        }

        $authTriggers = [
            'sign in',
            'log in',
            'login required',
            'use --cookies',
            'confirm you’re not a bot',
            'confirm you\'re not a bot',
            'authentication required',
        ];
        $privateTriggers = [
            'private video',
            'this video is private',
            'members-only',
            'members only',
            'join this channel',
        ];
        $ageOrGeoTriggers = [
            'sign in to confirm your age',
            'age-restricted',
            'confirm your age',
            'not available in your country',
            'geo-restricted',
            'geographic restriction',
        ];
        $networkTriggers = [
            'temporary failure in name resolution',
            'name resolution',
            'network is unreachable',
            'connection refused',
            'timed out',
            'timeout',
        ];
        $cookiesTriggers = [
            'cookies file could not be loaded',
            'cookies file is not in netscape format',
            'failed to parse cookies',
        ];
        $unavailableTriggers = [
            'this video is not available',
            'video unavailable',
            'video is unavailable',
            'this video has been removed',
            'video not found',
        ];

        if ($this->containsAny($normalized, $cookiesTriggers)) {
            return ['error_code' => 'cookies_invalid', 'auth_required' => true, 'error' => $error];
        }
        if ($this->containsAny($normalized, $ageOrGeoTriggers)) {
            return ['error_code' => 'age_or_geo_restricted', 'auth_required' => true, 'error' => $error];
        }
        if ($this->containsAny($normalized, $privateTriggers)) {
            return ['error_code' => 'private_or_blocked', 'auth_required' => true, 'error' => $error];
        }
        if ($this->containsAny($normalized, $authTriggers)) {
            return ['error_code' => 'auth_required', 'auth_required' => true, 'error' => $error];
        }
        if ($this->containsAny($normalized, $networkTriggers)) {
            return ['error_code' => 'network_error', 'auth_required' => false, 'error' => $error];
        }
        if ($this->containsAny($normalized, $unavailableTriggers)) {
            return ['error_code' => 'unavailable', 'auth_required' => false, 'error' => $error];
        }

        return ['error_code' => 'unknown_error', 'auth_required' => false, 'error' => $error];
    }

    /**
     * @param string[] $needles
     */
    private function containsAny(string $haystack, array $needles): bool {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function determineProbeDownloadableState(array $classification): ?bool
    {
        $errorCode = trim((string)($classification['error_code'] ?? ''));
        if ($errorCode === 'network_error') {
            return null;
        }

        return false;
    }
}
