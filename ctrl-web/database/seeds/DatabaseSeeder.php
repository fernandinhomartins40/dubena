<?php

use Illuminate\Database\Seeder;

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
        \DB::table('bairros')->delete();
        \DB::table('ruas')->delete();
        \DB::table('cidades')->delete();
        \DB::table('estados')->delete();
        \DB::table('empresas_grupos')->delete();
        \DB::table('menuusers')->delete();
        \DB::table('menus')->delete();
        \DB::table('nfgrupofiscals')->delete();
        \DB::table('spedtipoitems')->delete();
        \DB::table('creditopiscofins')->delete();
        \DB::table('nfcofins')->delete();
        \DB::table('valegassituacaos')->delete();
        \DB::table('checklisttipos')->delete();
        \DB::table('chequesituacaos')->delete();
        \DB::table('contamovimentotipos')->delete();
        \DB::table('contatipos')->delete();
        \DB::table('nfsituacaos')->delete();
        \DB::table('nfcests')->delete();
        \DB::table('ocorrenciasremessas')->delete();
        $this->call(EmpresasGruposTableSeeder::class);
        $this->call(EstadosTableSeeder::class);
        $this->call(CidadesTablePRSeeder::class);
        $this->call(CidadesTableSPSeeder::class);
        $this->call(BairrosTableSeeder::class);
        $this->call(EmpresasTableSeeder::class);
        $this->call(RuasTableSeeder::class);
        $this->call(UserTableSeeder::class);
        $this->call(GrupoFiscalSeeder::class);
        $this->call(spedtipoitemsTableSeeder::class);
        $this->call(PisCofinsCredTableSeeder::class);
        $this->call(NfCofinsTableSeeder::class);
        $this->call(CodigoIbgeTableSeeder::class);
        $this->call(ValegasSituacaosSeeder::class);
        $this->call(ChecklistTipoTableSeeder::class);
        $this->call(ChequeSituacaoTableSeeder::class);
        $this->call(ContamovimentotiposSeeder::class);
        $this->call(ContaTiposTableSeeder::class);
        $this->call(MenuTableSeeder::class);
        $this->call(NewMenusSeeder::class);
        $this->call(NfcestsTableSeeder::class);
        $this->call(NfsituacaosTableSeeder::class);
        $this->call(PopulateOcorrenciasRemessasSeeder::class);
        $this->call(AlterSequencesSeeder::class);
    }

}
