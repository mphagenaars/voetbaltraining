<?php
declare(strict_types=1);

class MatchTacticVideoTranscoder {
    private string $ffmpegPath;

    public function __construct() {
        $this->ffmpegPath = $this->findExecutable('ffmpeg');
    }

    public function isAvailable(): bool {
        return $this->ffmpegPath !== '';
    }

    /**
     * @return array{ok: bool, path?: string, error?: string}
     */
    public function transcodeToMp4(string $inputPath, string $outputPath, int $timeoutSeconds = 180): array {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'ffmpeg niet beschikbaar op de server.'];
        }

        if (!is_file($inputPath) || !is_readable($inputPath)) {
            return ['ok' => false, 'error' => 'Invoerbestand voor video-conversie ontbreekt.'];
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir) && !@mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            return ['ok' => false, 'error' => 'Kon geen tijdelijke map aanmaken voor MP4-output.'];
        }

        $cmd = [
            $this->ffmpegPath,
            '-y',
            '-i',
            $inputPath,
            '-an',
            '-c:v',
            'libx264',
            '-preset',
            'veryfast',
            '-crf',
            '26',
            '-pix_fmt',
            'yuv420p',
            '-movflags',
            '+faststart',
            $outputPath,
        ];

        $result = $this->runProcess($cmd, max(30, $timeoutSeconds));
        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string)($result['error'] ?? 'Video-conversie mislukt.')];
        }

        clearstatcache(true, $outputPath);
        if (!is_file($outputPath) || (int)@filesize($outputPath) <= 0) {
            return ['ok' => false, 'error' => 'MP4-conversie leverde geen geldig bestand op.'];
        }

        return ['ok' => true, 'path' => $outputPath];
    }

    /**
     * @param string[] $cmd
     * @return array{ok: bool, output?: string, error?: string}
     */
    private function runProcess(array $cmd, int $timeoutSeconds): array {
        $escapedCmd = implode(' ', array_map('escapeshellarg', $cmd));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($escapedCmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['ok' => false, 'error' => 'Kon ffmpeg-proces niet starten.'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();

        while (true) {
            $status = proc_get_status($process);
            if (!($status['running'] ?? false)) {
                $stdout .= stream_get_contents($pipes[1]) ?: '';
                $stderr .= stream_get_contents($pipes[2]) ?: '';
                break;
            }

            if ((time() - $start) > $timeoutSeconds) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return ['ok' => false, 'error' => 'Video-conversie timeout na ' . $timeoutSeconds . ' seconden.'];
            }

            $stdout .= fread($pipes[1], 8192) ?: '';
            $stderr .= fread($pipes[2], 8192) ?: '';
            usleep(50000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = (int)($status['exitcode'] ?? proc_close($process));
        if ($exitCode !== 0) {
            $message = trim($stderr) !== '' ? trim($stderr) : ('Exit code ' . $exitCode);
            if (strlen($message) > 500) {
                $message = substr($message, 0, 500) . '...';
            }
            return ['ok' => false, 'error' => $message];
        }

        return ['ok' => true, 'output' => $stdout];
    }

    private function findExecutable(string $name): string {
        $paths = [
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
        ];

        foreach ($this->resolveCandidateHomeDirs() as $homeDir) {
            $paths[] = rtrim($homeDir, '/\\') . '/.local/bin/' . $name;
        }

        $paths = array_values(array_unique($paths));
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        $whichResult = @shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null');
        if (is_string($whichResult) && trim($whichResult) !== '') {
            $resolvedPath = trim($whichResult);
            if (is_file($resolvedPath) && is_executable($resolvedPath)) {
                return $resolvedPath;
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function resolveCandidateHomeDirs(): array {
        $homes = [];

        $serverHome = $_SERVER['HOME'] ?? null;
        $envHome = getenv('HOME');
        if (is_string($serverHome) && trim($serverHome) !== '') {
            $homes[] = trim($serverHome);
        }
        if (is_string($envHome) && trim($envHome) !== '') {
            $homes[] = trim($envHome);
        }

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $runtimeUser = @posix_getpwuid((int)posix_geteuid());
            if (is_array($runtimeUser) && isset($runtimeUser['dir']) && is_string($runtimeUser['dir']) && trim($runtimeUser['dir']) !== '') {
                $homes[] = trim($runtimeUser['dir']);
            }

            $projectRoot = dirname(__DIR__, 2);
            $ownerId = @fileowner($projectRoot);
            if (is_int($ownerId) && $ownerId >= 0) {
                $owner = @posix_getpwuid($ownerId);
                if (is_array($owner) && isset($owner['dir']) && is_string($owner['dir']) && trim($owner['dir']) !== '') {
                    $homes[] = trim($owner['dir']);
                }
            }
        }

        $unique = [];
        foreach ($homes as $home) {
            if (!in_array($home, $unique, true)) {
                $unique[] = $home;
            }
        }

        return $unique;
    }
}
