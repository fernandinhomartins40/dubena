<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class AppVideoService
{

    /**
     * @var $ffmpeg - Bin location of ffmpeg
     */
    protected $ffmpeg;

    /**
     * @var $ffprobe - Bin location of ffprobe
     */
    protected $ffprobe;

    /**
     * @var $ffmpeg - Timeout in seconds
     */
    protected $timeout;


    public function __construct(int $timeout = 10)
    {
        $this->ffmpeg = config("services.ffmpeg.bin");
        $this->ffprobe = config("services.ffmpeg.probe");
        $this->timeout = $timeout;
    }

    public function getDuration($path)
    {
        $path = $this->normalizePath($path);

        $result = $this->runCommand([
            $this->ffprobe,
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=noprint_wrappers=1:nokey=1',
            $path
        ]);

        return floatval($result);
    }

    public function getResolution($path)
    {
        $file = $this->normalizePath($path);

        $result = $this->runCommand([
            $this->ffprobe,
            '-v',
            'error',
            '-select_streams',
            'v:0',
            '-show_entries',
            'stream=width,height',
            '-of',
            'csv=p=0',
            $file
        ]);

        [$width, $height] = explode(",", $result);

        return (object) [
            "width" => intval($width),
            "height" => intval($height),
        ];
    }

    /**
     * @param string $input The initial path of the video
     * @param string $output The output path where the video will be save
     * @return void
     */
    public function optimizeAndSave($input, $output)
    {
        $inputFile = $this->normalizePath($input);
        $outputFile = $this->normalizePath($output);

        $cmd = [
            $this->ffmpeg,
            '-nostdin',
            '-y',
            '-i',
            $inputFile,
            '-vf',
            'scale=1080:-1',
            '-c:v',
            'libx264',
            '-profile:v',
            'main',
            '-preset',
            'medium',
            '-crf',
            '25',
            '-movflags',
            '+faststart',
            $outputFile
        ];


        $output = $this->runCommand($cmd);
    }

    private function runCommand($cmd)
    {
        $process = new Process($cmd);

        $process->setTimeout($this->timeout);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception("Error: " . $process->getErrorOutput());
        }

        return trim($process->getOutput());
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
