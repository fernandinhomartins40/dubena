<?php

namespace App\Jobs;

use App\Appvideo;
use App\Enums\AppVideoStatus;
use App\Http\Controllers\AppvideoController;
use Illuminate\Bus\Queueable;
use App\Services\AppVideoService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessAppVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string $tmpPath - Temporary path of the file to be processed
     */
    private $tmpPath;

    /**
     * @var string $outputPath - Path to save the new file
     */
    private $outputPath;

    /**
     * @var Appvideo $appvideo - Model that contains the video record
     */
    private $appvideo;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $tmpPath, string $outputPath, Appvideo $appvideo)
    {
        $this->tmpPath = $tmpPath;
        $this->outputPath = $outputPath;
        $this->appvideo = $appvideo;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->appvideo->update([
            "status" => AppVideoStatus::EmProcessamento,
        ]);

        $this->processVideo();

        $this->appvideo->update([
            "status" => AppVideoStatus::Processado,
            "caminho" => Storage::url("video/startup_video.mp4")
        ]);

        $this->syncApi();
    }

    public function failed(\Exception $ex)
    {
        $this->appvideo->update([
            "status" => AppVideoStatus::ErroProcessamento,
            "mensagem" => str_limit($ex->getMessage(), 390)
        ]);

        info("Error processamento: " . $ex->getMessage());
    }

    private function processVideo()
    {
        $videoService = new AppVideoService(300);

        $videoService->optimizeAndSave($this->tmpPath, $this->outputPath);

        @unlink($this->tmpPath);
    }

    private function syncApi()
    {
        $controller = new AppvideoController();

        $payload = [
            "url" => asset($this->appvideo->caminho),
            "titulo" => $this->appvideo->titulo,
            "ativo" => true,
        ];

        $controller->syncApi($payload);

        $this->appvideo->update([
            "status" => AppVideoStatus::Sincronizado,
        ]);
    }
}
