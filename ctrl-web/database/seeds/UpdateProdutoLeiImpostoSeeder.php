<?php

use Carbon\Carbon;
use Illuminate\Database\Seeder;

class UpdateProdutoLeiImpostoSeeder extends Seeder
{

    /**
     * @var string
     */
    private $tempFile = "temp.ibptseed";

    /**
     * @throws Throwable
     */
    public function run()
    {
        $this->start();
        $files = glob(csvIbptDir() . "*.json");
        if (! $files) {
            $this->log("No files found.");
            $this->finish();
            return;
        }
        if (Storage::disk("ibpt")->has($this->tempFile)) {
            $this->log("Script is already running");
            $this->finish(false);
            return;
        }

        $len = count($files);
        $i = 1;
        foreach ($files as $file) {
            $this->insertFromFile($file, $i, $len);
            $i++;
        }
        $this->finish();
    }

    /**
     *
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
        echo $msg . PHP_EOL;
        if (! file_exists(csvIbptDir() . "insert.log")) {
            Storage::disk("ibpt")->put("insert.log", $msg . PHP_EOL);
        } else {
            Storage::disk("ibpt")->append("insert.log", $msg . PHP_EOL);
        }
    }
}
