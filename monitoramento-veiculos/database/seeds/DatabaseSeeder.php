<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    /*
      SELECT
      CONCAT(
      'Menu::create([\'id\' => ',a.id,', \'parent_id\' => ',IF(a.parent_id IS NULL,'NULL',a.parent_id),', \'titulo\' => \'',a.titulo,'\', \'descricao\'=>\'',IF(a.descricao IS NULL,'NULL',a.descricao),'\',\'ordem\'=>',a.ordem,']);'
      ) FROM menus a order by a.parent_id;
     */
    public function run()
    {
        \DB::table('users')->delete();
        \DB::table('empresas')->delete();
        \DB::table('empresas_grupos')->delete();
        \DB::table('menu_user')->delete();
        \DB::table('menus')->delete();
        \DB::table('configs')->delete();
        \DB::table('ultimaposicaos')->delete();
		\DB::table('veiculotipos')->delete();
        $this->call(EmpresasGruposTableSeeder::class);
        $this->call(EmpresasTableSeeder::class);
        $this->call(UserTableSeeder::class);
        $this->call(MenuTableSeeder::class);
        $this->call(VeiculotiposTableSeeder::class);
        $this->call(ConfigTableSeeder::class);
        //$this->call(UltimaposicaosTableSeeder::class);
        //$this->call(AlterSequencesSeeder::class);
    }

}
