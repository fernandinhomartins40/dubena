<?php

namespace App\Http\Controllers;

use DB;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class IBPTController extends Controller
{

    /**
     * @var array
     */
    private $ignored = [
//        "AC" => "Acre",
//        "AL" => "Alagoas",
//        "AM" => "Amazonas",
//        "AP" => "Amapá",
//        "BA" => "Bahia",
//        "CE" => "Ceará",
//        "DF" => "Distrito Federal",
//        "ES" => "Espírito Santo",
//        "GO" => "Goiás",
//        "MA" => "Maranhão",
//        "MG" => "Minas Gerais",
//        "MS" => "Mato Grosso do Sul",
//        "MT" => "Mato Grosso",
//        "PA" => "Pará",
//        "PB" => "Paraíba",
//        "PE" => "Pernambuco",
//        "PI" => "Piauí",
//        "RJ" => "Rio de Janeiro",
//        "RN" => "Rio Grande do Norte",
//        "RO" => "Rondônia",
//        "RR" => "Roraima",
//        "RS" => "Rio Grande do Sul",
//        "SC" => "Santa Catarina",
//        "SE" => "Sergipe",
//        "SP" => "São Paulo",
//        "TO" => "Tocantins"
    ];

    /**
     * @var string
     */
    private $prefixFile = "TABELAIBPTAX";

    /**
     * @var string
     */
    private $command = "ibpt:files";

    /**
     * @var bool
     */
    private $exists = false;

    /**
     * @var Collection
     */
    private $nonExistents;

    /**
     * @var string
     */
    private $path;

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $data = DB::table("produtoleiimpostos")
            ->selectRaw("unique(uf) as uf, versao, chave, inicio, fim")
            ->groupBy("uf", "versao", "chave", "inicio", "fim")
            ->orderBy("uf")
            ->orderBy("inicio")
            ->get();
        return view("nf.ibpt.index", compact("data"));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function store(Request $request)
    {
        try {

            DB::beginTransaction();

            //precisa alterar o tamanho maximo de upload de arquivos no php.ini, coloquei para 10MB pra ser garantido que funcione
            $file = $request->file("file-upload", null);

            $this->path = csvIbptDir();

            if (\Storage::disk("ibpt")->has("temp.ibptseed")) {
                throw new Exception("Há um processo de inserção dos impostos rodando no servidor, tente novamente mais tarde.");
            }

            if (! $file) {
                throw new Exception("Erro ao buscar o arquivo.");
            }

            $extracted = $this->extractTo($file);
            if ($extracted !== true) {
                throw new Exception($extracted);
            }

            $files = glob($this->path . "*.csv");

            if (! $files) {
                throw new Exception("Nenhum arquivo .csv encontrado.");
            }

            $this->changeFileNames($files);

            \Artisan::call($this->command);

            $err = \Storage::disk("ibpt")->get("ibpt.err");

            if ($err && $err !== "\r\n" && $err !== "\r" && $err !== "\n") {
                throw new Exception($err);
            } else {
                $msg = "Arquivos importados com sucesso, o sistema irá processar as informações" .
                    " e as novas tabelas estarão disponíveis amanhã. ";
            }

            DB::commit();
            return response()->json(["msg" => $msg, "status" => "OK|"]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(["msg" => $e->getMessage()]);
        }
    }

    /**
     * @param $files
     * @throws Exception
     */
    private function changeFileNames($files)
    {
        $this->nonExistents = collect([]);
        $this->eachEstados(function ($uf, $description) use ($files) {
            $this->exists = false;
            $this->eachFiles($files, function ($file) use ($uf) {
                if ($this->getUfFromFile($file) === strtoupper($uf)) {
                    $this->exists = true;
                }
                if (file_exists($file)) {
                    $newName = $this->path . $this->getUfFromFile($file) . ".csv";
                    rename($file, $newName);
                }
            });

            if (! $this->exists && ! isset($this->ignored[$uf])) {
                $this->nonExistents->push($description);
            }
        });
        if ($this->nonExistents->count()) {
            throw new Exception("Arquivo .csv não encontrado para os estados: " . $this->nonExistents->implode(", "));
        }
    }

    /**
     * @param $file
     * @return string
     */
    private function getUfFromFile($file)
    {
        $search = [strtoupper($this->path), $this->prefixFile];
        return strtoupper(substr(str_replace($search, "", strtoupper($file)), 0, 2));
    }

    /**
     * @param $callback
     */
    private function eachEstados($callback)
    {
        $estados = getEstados();
        foreach ($estados as $uf => $description) {
            call_user_func_array($callback, [$uf, $description]);
        }
    }

    /**
     * @param $files
     * @param $callback
     */
    private function eachFiles($files, $callback)
    {
        foreach ($files as $file) {
            call_user_func($callback, $file);
        }
    }

    /**
     * @param UploadedFile $file
     * @return bool|string
     */
    private function extractTo(UploadedFile $file)
    {
        $zip = new ZipArchive();
        $res = $zip->open($file->getRealPath());

        if ($res === TRUE) {
            $fileS = new Filesystem();
            $fileS->cleanDirectory($this->path);
            $zip->extractTo($this->path);
            $zip->close();
            return true;
        } else {
            $msg = "Erro ao extrair arquivo, certifique-se de que o sistema foi configurado " .
                "para receber upload de arquivos grandes: ";
            switch ( (int) $res )
            {
                case ZipArchive::ER_OK           : return 'N No error';
                case ZipArchive::ER_MULTIDISK    : return 'N Multi-disk zip archives not supported';
                case ZipArchive::ER_RENAME       : return 'S Renaming temporary file failed';
                case ZipArchive::ER_CLOSE        : return 'S Closing zip archive failed';
                case ZipArchive::ER_SEEK         : return 'S Seek error';
                case ZipArchive::ER_READ         : return 'S Read error';
                case ZipArchive::ER_WRITE        : return 'S Write error';
                case ZipArchive::ER_CRC          : return 'N CRC error';
                case ZipArchive::ER_ZIPCLOSED    : return 'N Containing zip archive was closed';
                case ZipArchive::ER_NOENT        : return 'N No such file';
                case ZipArchive::ER_EXISTS       : return 'N File already exists';
                case ZipArchive::ER_OPEN         : return 'S Can\'t open file';
                case ZipArchive::ER_TMPOPEN      : return 'S Failure to create temporary file';
                case ZipArchive::ER_ZLIB         : return 'Z Zlib error';
                case ZipArchive::ER_MEMORY       : return 'N Malloc failure';
                case ZipArchive::ER_CHANGED      : return 'N Entry has been changed';
                case ZipArchive::ER_COMPNOTSUPP  : return 'N Compression method not supported';
                case ZipArchive::ER_EOF          : return 'N Premature EOF';
                case ZipArchive::ER_INVAL        : return 'N Invalid argument';
                case ZipArchive::ER_NOZIP        : return 'N Not a zip archive';
                case ZipArchive::ER_INTERNAL     : return 'N Internal error';
                case ZipArchive::ER_INCONS       : return 'N Zip archive inconsistent';
                case ZipArchive::ER_REMOVE       : return 'S Can\'t remove file';
                case ZipArchive::ER_DELETED      : return 'N Entry has been deleted';
                default: return $msg . " status desconhecido para o código de erro " . $res;
            }
        }
    }
}
