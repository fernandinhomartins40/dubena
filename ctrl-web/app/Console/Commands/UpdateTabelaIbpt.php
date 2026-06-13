<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use DB;
use Exception;
use File;
use Illuminate\Console\Command;
use Storage;

class UpdateTabelaIbpt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ibpt:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run script files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (Storage::disk("ibpt")->has($this->tempFile)) {
            $this->log("Script is already running");
            $this->finish(false);
            return;
        }
        $this->start();
        $files = glob(csvIbptDir() . "*.json");
        if (! $files) {
            $this->log("No files found.");
            $this->finish();
            return;
        }

        $len = count($files);
        $i = 1;
        foreach ($files as $file) {
            $this->insertFromFile($file, $i, $len);
            $i++;
        }
        $this->finish();
        return;
    }
    /**
     * @var string
     */
    private $tempFile = "temp.ibptseed";

    /**
     * @param bool $deleteTemp
     */
    private function finish($deleteTemp = true)
    {
        $this->log("finished at " . Carbon::now()->toDateTimeString());
        if ($deleteTemp) {
            Storage::disk("ibpt")->delete($this->tempFile);
        }
    }

    /**
     * @param $file
     * @param $i
     * @param $len
     */
    private function insertFromFile($file, $i, $len)
    {
        try {
            $uf = File::name($file);
            $this->log("running script of " . $uf . " [" . $i . " of " . $len . "]");

            $collection = collect(json_decode(File::get($file), true));

            DB::beginTransaction();

            DB::table("produtoleiimpostos")->where("uf", $uf)->delete();
            foreach ($collection->chunk(1000) as $items) {
                DB::table("produtoleiimpostos")->insert($items->toArray());
            }

            DB::commit();
            File::delete($file);
        } catch (Exception $e) {
            DB::rollBack();
            $this->log($e->getMessage());
        }
    }

    /**
     *
     */
    private function start()
    {
        $this->log("started at " . Carbon::now()->toDateTimeString());
        Storage::disk("ibpt")->put($this->tempFile, "init");
    }

    /**
     * @param $msg
     */
    private function log($msg)
    {
        $this->info($msg . PHP_EOL);
        if (! file_exists(csvIbptDir() . "insert.log")) {
            Storage::disk("ibpt")->put("insert.log", $msg . " | " . PHP_EOL);
        } else {
            Storage::disk("ibpt")->append("insert.log", $msg . " | " . PHP_EOL);
        }
    }
}
