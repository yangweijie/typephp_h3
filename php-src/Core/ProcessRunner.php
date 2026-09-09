<?php

/**
 * H3PHP — Process Runner.
 *
 * Manages subprocess execution for FFmpeg muxing and external tools.
 * Follows the pattern from aot-compiler's NativeBuilder.
 *
 * Used for:
 *   - FFmpeg H.264 + AAC MP4 muxing
 *   - Real-ESRGAN super-resolution
 *   - Video probing (ffprobe)
 */

namespace H3Php\Core;

class ProcessRunner
{
    /** Path to ffmpeg binary */
    private string $ffmpegPath;

    /** Path to ffprobe binary */
    private string $ffprobePath;

    /** Last command output */
    private string $lastOutput = '';

    /** Last command exit code */
    private int $lastExitCode = 0;

    public function __construct(
        string $ffmpegPath = 'ffmpeg',
        string $ffprobePath = 'ffprobe',
    ) {
        $this->ffmpegPath = $ffmpegPath;
        $this->ffprobePath = $ffprobePath;
    }

    /**
     * Mux RGB frames + PCM audio into H.264 + AAC MP4.
     *
     * @param string                                                     $outputPath Output MP4 file path
     * @param array{frames: string[], width: int, height: int, fps: int} $video      Video data
     * @param array{pcm: string, sample_rate: int, channels: int}|null   $audio      Audio data (optional)
     *
     * @throws \RuntimeException If output directory cannot be created
     *
     * @return bool Success
     */
    public function muxToMp4(string $outputPath, array $video, ?array $audio = null): bool
    {
        $width = $video['width'];
        $height = $video['height'];
        $fps = $video['fps'];
        $numFrames = count($video['frames']);

        // Nothing to mux: ffmpeg would just block waiting on an empty stdin.
        if (0 === $numFrames) {
            $this->lastOutput = 'No frames to encode';
            $this->lastExitCode = 1;

            return false;
        }

        // Build ffmpeg command
        $cmd = [
            $this->ffmpegPath,
            '-y', // Overwrite output
            '-f', 'rawvideo',
            '-pixel_format', 'rgb24',
            '-video_size', "{$width}x{$height}",
            '-framerate', (string) $fps,
            '-i', 'pipe:0', // RGB frames from stdin
        ];

        // Add audio if provided
        if (null !== $audio) {
            $cmd = array_merge($cmd, [
                '-f', 'f32le',
                '-ar', (string) $audio['sample_rate'],
                '-ac', (string) $audio['channels'],
                '-i', 'pipe:1', // PCM audio from second stdin
            ]);
        }

        // Output settings
        $cmd = array_merge($cmd, [
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '18',
            '-pix_fmt', 'yuv420p',
        ]);

        if (null !== $audio) {
            $cmd = array_merge($cmd, [
                '-c:a', 'aac',
                '-b:a', '192k',
            ]);
        }

        $cmd[] = $outputPath;

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                throw new \RuntimeException("Cannot create output directory: {$outputDir}");
            }
        }

        // Execute ffmpeg with stdin pipe for RGB frame data
        $descriptors = [
            0 => ['pipe', 'r'], // stdin — RGB frames
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $cmdString = implode(' ', array_map('escapeshellarg', $cmd));
        $process = proc_open($cmdString, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start ffmpeg process');
        }

        // Write RGB frames to stdin
        $frameCount = count($video['frames']);
        foreach ($video['frames'] as $frameData) {
            fwrite($pipes[0], $frameData);
        }
        fclose($pipes[0]);

        // Read output — both pipes must be drained together (see drainPipes()).
        [$stdout, $stderr] = $this->drainPipes($process, $pipes);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->lastOutput = $stdout . $stderr;
        $this->lastExitCode = $exitCode;

        return 0 === $exitCode;
    }

    /**
     * Run ffprobe to get video information.
     *
     * @param string $videoPath Path to video file
     *
     * @return array{duration: float, width: int, height: int, fps: int, codec: string}|null
     */
    public function probeVideo(string $videoPath): ?array
    {
        $cmd = [
            $this->ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $videoPath,
        ];

        $output = '';
        $exitCode = $this->executeCommand($cmd, $output);

        if (0 !== $exitCode) {
            return null;
        }

        $data = json_decode($output, true);
        if (null === $data) {
            return null;
        }

        // Extract video stream info
        $videoStream = null;
        foreach ($data['streams'] ?? [] as $stream) {
            if ('video' === $stream['codec_type']) {
                $videoStream = $stream;
                break;
            }
        }

        if (null === $videoStream) {
            return null;
        }

        // Parse frame rate
        $fpsParts = explode('/', $videoStream['r_frame_rate'] ?? '24/1');
        $fps = 2 === count($fpsParts)
            ? (int) $fpsParts[0] / (int) $fpsParts[1]
            : (int) $fpsParts[0];

        return [
            'duration' => (float) ($data['format']['duration'] ?? 0),
            'width' => (int) ($videoStream['width'] ?? 0),
            'height' => (int) ($videoStream['height'] ?? 0),
            'fps' => (int) $fps,
            'codec' => $videoStream['codec_name'] ?? 'unknown',
        ];
    }

    /**
     * Run Real-ESRGAN for super-resolution.
     *
     * @param string $inputPath  Input image/video path
     * @param string $outputPath Output path
     * @param string $srBinPath  Path to realesrgan-ncnn-vulkan binary
     * @param string $modelDir   Models directory
     * @param string $modelName  Model name
     * @param int    $scale      Upscale factor (2-4)
     *
     * @return bool Success
     */
    public function superResolve(
        string $inputPath,
        string $outputPath,
        string $srBinPath,
        string $modelDir,
        string $modelName = 'realesrgan-x4plus',
        int $scale = 4,
    ): bool {
        $cmd = [
            $srBinPath,
            '-i', $inputPath,
            '-o', $outputPath,
            '-n', $modelName,
            '-s', (string) $scale,
            '-m', $modelDir,
        ];

        $output = '';
        $exitCode = $this->executeCommand($cmd, $output);

        return 0 === $exitCode;
    }

    /**
     * Execute a command and capture output.
     *
     * @param array  $command Command and arguments
     * @param string &$output Captured stdout+stderr
     *
     * @return int Exit code
     */
    public function executeCommand(array $command, string &$output = ''): int
    {
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $cmdString = implode(' ', array_map('escapeshellarg', $command));
        $process = proc_open($cmdString, $descriptors, $pipes);

        if (!is_resource($process)) {
            $output = 'Failed to start process';

            return 1;
        }

        // Close stdin immediately (we're not writing to it in this mode)
        fclose($pipes[0]);

        // Read output — both pipes must be drained together (see drainPipes()).
        [$stdout, $stderr] = $this->drainPipes($process, $pipes);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->lastOutput = $stdout . $stderr;
        $this->lastExitCode = $exitCode;
        $output = $this->lastOutput;

        return $exitCode;
    }

    /**
     * Drain stdout and stderr concurrently.
     *
     * Reading them one after the other deadlocks: tools like ffmpeg write
     * their log to stderr, so once that pipe fills up the child blocks and
     * never closes stdout, leaving a blocking stdout read waiting forever.
     *
     * Pipes are polled in non-blocking mode instead of using stream_select(),
     * which does not work with process pipes on Windows.
     *
     * @param resource             $process proc_open() resource
     * @param array<int, resource> $pipes   proc_open() pipe array
     *
     * @return array{0: string, 1: string} stdout and stderr contents
     */
    private function drainPipes($process, array $pipes): array
    {
        $stdout = '';
        $stderr = '';

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $stdout .= (string) fread($pipes[1], 8192);
            $stderr .= (string) fread($pipes[2], 8192);

            $status = proc_get_status($process);
            if (!$status['running']) {
                // Child exited — drain whatever is still buffered.
                do {
                    $out = (string) fread($pipes[1], 8192);
                    $err = (string) fread($pipes[2], 8192);
                    $stdout .= $out;
                    $stderr .= $err;
                } while ('' !== $out || '' !== $err);

                break;
            }

            usleep(1000);
        }

        return [$stdout, $stderr];
    }

    /**
     * Get the last command output.
     */
    public function getLastOutput(): string
    {
        return $this->lastOutput;
    }

    /**
     * Get the last exit code.
     */
    public function getLastExitCode(): int
    {
        return $this->lastExitCode;
    }

    /**
     * Check if ffmpeg is available.
     */
    public function isFfmpegAvailable(): bool
    {
        $output = '';
        $code = $this->executeCommand([$this->ffmpegPath, '-version'], $output);

        return 0 === $code;
    }

    /**
     * Check if ffprobe is available.
     */
    public function isFfprobeAvailable(): bool
    {
        $output = '';
        $code = $this->executeCommand([$this->ffprobePath, '-version'], $output);

        return 0 === $code;
    }
}
