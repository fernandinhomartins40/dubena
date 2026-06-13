<?php

use App\Menu;
use Illuminate\Database\Seeder;

class MenuTableSeeder extends Seeder
{

    public function __construct()
    {
        if (! defined("JSON_DIRECTORY")) {
            define("JSON_DIRECTORY", __DIR__ . DIRECTORY_SEPARATOR . "json" . DIRECTORY_SEPARATOR);
        }
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Menu::truncate();
        $this->make($this->getMenus());
    }

    private function getMenus()
    {
        return json_decode(file_get_contents(JSON_DIRECTORY . "menus.json"));
    }

    private function make($menus, $parent = null)
    {
        foreach ($menus as $menu) {
            $this->insert($menu, $parent);
        }
    }

    public function insert($menu, $parent = null)
    {
        $attr = [
            'parent_id' => $parent,
            'titulo'    => $menu->titulo,
            'descricao' => $menu->descricao
        ];
        $new = Menu::create($attr);
        if (! $new) {
            dd('travou: ', $menu);
        }

        if (isset($menu->childrens) && is_array($menu->childrens) && count($menu->childrens)) {
            $this->make($menu->childrens, $new->id);
        }
        return $new;
    }
}
