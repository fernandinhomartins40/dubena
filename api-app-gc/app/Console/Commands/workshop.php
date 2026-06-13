<?php

namespace App\Console\Commands;

use Artisan;
use Illuminate\Console\Command;

class workshop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workshop:module {name} {--a}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creating a station of work';

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
        if ($this->option("a")) {
            $controller = "y";
            $request = "y";
            $repository = "y";
            $view = "y";
            $vue = "y";
        } else {
            $controller = $this->choice("Gererate Controller?", ["y", "n"]);
            $request = $this->choice("Gererate Request?", ["y", "n"]);
            $repository = $this->choice("Gererate Repository?", ["y", "n"]);
            $view = $this->choice("Gererate view?", ["y", "n"]);
            $vue = $this->choice("Gererate vue?", ["y", "n"]);
        }

        $name = $this->argument("name");
        $s = DIRECTORY_SEPARATOR;

        if ($request === "y") {
            $path = base_path("app" .  $s . "Http" . $s . "Requests");
            $this->makeFile($name, "Request", "Request", $path);
        }

        if ($repository === "y") {
            $path = base_path("app" . $s . "Repository");
            $this->makeFile($name, "Repository", "Repository", $path);
        }

        if ($controller === "y") {
            $path = base_path("app" . $s . "Http" . $s . "Controllers");
            $this->makeFile($name, "Controller", "Controller", $path);
        }

        if ($view === "y") {
            $path = base_path("resources" . $s . "views" . $s . strtolower($name));
            $this->makeFile("index", "", "View", $path, ".blade.php");
        }

        if ($vue === "y") {
            $path = base_path("resources" . $s . "assets" . $s . "js" . $s . "components" . $s . strtolower($name));
            $this->makeFile($name, "", "Vue", $path, ".vue");

            $path = base_path("resources" . $s . "assets" . $s . "js");
            $this->makeFile($name, "", "Js", $path, ".js");

            $path = base_path("public" . $s . "js");
            $this->makeFile($name, "", "VueJs", $path, ".js");
        }

    }

    private function makeFile($name, $extraName, $type, $path, $fileExtension = ".php")
    {
        $s = DIRECTORY_SEPARATOR;

        if (! is_dir($path)) {
            mkdir($path, 0777);
        }

        $contents = file_get_contents(__DIR__ . $s . "stubs" . $s . $type . ".stub");

        $contents = str_replace("__Name", $name, $contents);
        $contents = str_replace("__name", strtolower($name), $contents);

        $filename = $path . $s
            . ($name === "index" || in_array($type, ["VueJs", "Js"]) ? strtolower($name) : $name)
            . $extraName . $fileExtension;

        if (file_exists($filename)) {
            $ask = $this->ask("file " . $filename . " exists, overwrite? [yes, no]");
            if ($ask === "yes") {
                $this->writeFile($filename, $contents);
            }
        } else {
            $this->writeFile($filename, $contents);
        }
    }

    private function writeFile($filename, $contents)
    {
        $file = fopen($filename, "w");
        fwrite($file, $contents);
        fclose($file);
    }
}
