<?php
/**
 * H3PHP — Process Runner
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
        string $ffprobePath = 'ffprobe'
    ) {
        $this->ffmpegPath = $ffmpegPath;
        $this->ffprobePath = $ffprobePath;
    }

    /**
     * Mux RGB frames + PCM audio into H.264 + AAC MP4.
     *
     * @param string $outputPath Output MP4 file path
     * @param array{frames: string[], width: int, height: int, fps: int} $video Video data
     * @param array{pcm: string, sample_rate: int, channels: int}|null $audio Audio data (optional)
     * @return bool Success
     * @throws \RuntimeException If output directory cannot be created
     */
    public function muxToMp4(string $outputPath, array $video, ?array $audio = null): bool
    {
        $width = $video['width'];
        $height = $video['height'];
        $fps = $video['fps'];
        $numFrames = count($video['frames']);

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
        if ($audio !== null) {
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

        if ($audio !== null) {
            $cmd = array_merge($cmd, [
                '-c:a', 'aac',
                '-b:a', '192k',
            ]);
        }

        $cmd[] = $outputPath;

        // TODO: Execute with pipes for frame data
        // For now, return placeholder
        return true;
    }

    /**
     * Run ffprobe to get video information.
     *
     * @param string $videoPath Path to video file
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

        if ($exitCode !== 0) {
            return null;
        }

        $data = json_decode($output, true);
        if ($data === null) {
            return null;
        }

        // Extract video stream info
        $videoStream = null;
        foreach ($data['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $videoStream = $stream;
                break;
            }
        }

        if ($videoStream === null) {
            return null;
        }

        // Parse frame rate
        $fpsParts = explode('/', $videoStream['r_frame_rate'] ?? '24/1');
        $fps = count($fpsParts) === 2
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
     * @param string $inputPath Input image/video path
     * @param string $outputPath Output path
     * @param string $srBinPath Path to realesrgan-ncnn-vulkan binary
     * @param string $modelDir Models directory
     * @param string $modelName Model name
     * @param int $scale Upscale factor (2-4)
     * @return bool Success
     */
    public function superResolve(
        string $inputPath,
        string $outputPath,
        string $srBinPath,
        string $modelDir,
        string $modelName = 'realesrgan-x4plus',
        int $scale = 4
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

        return $exitCode === 0;
    }

    /**
     * Execute a command and capture output.
     *
     * @param array $command Command and arguments
     * @param string &$output Captured stdout+stderr
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

        // Read output
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->lastOutput = $stdout . $stderr;
        $this->lastExitCode = $exitCode;
        $output = $this->lastOutput;

        return $exitCode;
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
        return $code === 0;
    }

    /**
     * Check if ffprobe is available.
     */
    public function isFfprobeAvailable(): bool
    {
        $output = '';
        $code = $this->executeCommand([$this->ffprobePath, '-version'], $output);
        return $code === 0;
    }
}
