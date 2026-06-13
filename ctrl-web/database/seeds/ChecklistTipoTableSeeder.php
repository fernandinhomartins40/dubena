<?php

use App\Checklisttipo;
use Illuminate\Database\Seeder;

class ChecklistTipoTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Checklisttipo::create(['descricao'=>'Gestão Veicular','ativo'=>1]);
        Checklisttipo::create(['descricao'=>'Gestão de Lojas','ativo'=>1]);
    }
}
