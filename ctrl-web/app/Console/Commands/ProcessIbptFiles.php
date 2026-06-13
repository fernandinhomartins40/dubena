<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use \Exception;
use File;
use Illuminate\Console\Command;
use Storage;

class ProcessIbptFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ibpt:files {--not-remove}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get csv files content and put to sql file';

    /**
     * @var string
     */
    private $errFile = 'ibpt.err';

    /**
     * @var string
     */
    private $errors = '';

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
        try {

            set_time_limit(0);
            Storage::disk("ibpt")->append($this->errFile, $this->errors);
            $this->loadFiles();
            Storage::disk("ibpt")->append($this->errFile, $this->errors);

            return "Arquivos processados com sucesso";
        } catch (Exception $e) {
            $this->info($e->getMessage());
            $this->addError($e->getMessage());
            Storage::disk("ibpt")->append($this->errFile, $this->errors);
            return $e->getMessage();
        }
    }

    private function loadFiles()
    {
        $files = glob(csvIbptDir() . "*.csv");
        foreach ($files as $file) {
            $this->convertFile($file);
            File::delete($file);
        }
    }

    private function convertFile($file)
    {
        $row = 1;
        $uf = str_replace([".csv", csvIbptDir()], "", $file);
        $sqlFile = $uf . ".json";

        $json = "";
        if (($handle = fopen($file, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "\n")) !== false) {
                if ($row > 1) {
                    $data = explode(";", implode(",", $data));
                    $json .= ($row !== 2 ? "," : "") . $this->toJson($data, $uf);
                }
                $row++;
            }
            fclose($handle);
        }

        Storage::disk("ibpt")->put($sqlFile, "[" . $json . "]");
    }

    /**
     * @param $data
     * @param $uf
     * @return mixed
     * @throws Exception
     */
    private function toJson($data, $uf)
    {
        if (count($data) !== 13) {
            $msg = "ncm " . $data[0] . " do uf " . $uf . " retornou um resultado inesperado";
            $this->warn($msg);
            $message = "O arquivo do estado do "
                . $uf . " possui uma quantidade não esperada de colunas, " .
                "verifique o arquivo e tente novamente.";
            throw new Exception($message);
        }

        $collection = collect([]);

        $collection->put("ncm", $data[0]);
        $collection->put("uf", $uf);
        $collection->put("ex", $data[1] ? $data[1] : " ");
        $collection->put("tabela", $data[2]);
        $descricao = str_replace("\"", "", $data[3]);
        $descricao = mb_convert_encoding(utf8_encode($descricao), 'UTF-8', 'UTF-8');
        $collection->put("descricao", $descricao);
        $collection->put("aliqnac", $data[4]);
        $collection->put("aliqimp", $data[5]);
        $collection->put("aliqestadual", $data[6]);
        $collection->put("aliqmunicipal", $data[7]);
        $collection->put("inicio", insertDataOracle($data[8]) . " 00:00:00");
        $collection->put("fim", insertDataOracle($data[9]) . " 00:00:00");
        $collection->put("chave", $data[10]);
        $collection->put("versao", $data[11]);

        return str_replace("\\/", "/", $collection->toJson());
    }

    public function addError($error)
    {
        $this->errors .= $error . PHP_EOL;
    }
}
