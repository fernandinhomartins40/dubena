<?php

use Illuminate\Database\Seeder;

class EstadosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
      DB::table('estados')->insert(['uf' => 'AC', 'descricao' => 'Acre']);
      DB::table('estados')->insert(['uf' => 'AL', 'descricao' => 'Alagoas']);
      DB::table('estados')->insert(['uf' => 'AM', 'descricao' => 'Amazonas']);
      DB::table('estados')->insert(['uf' => 'AP', 'descricao' => 'Amapá']);
      DB::table('estados')->insert(['uf' => 'BA', 'descricao' => 'Bahia']);
      DB::table('estados')->insert(['uf' => 'CE', 'descricao' => 'Ceará']);
      DB::table('estados')->insert(['uf' => 'DF', 'descricao' => 'Distrito Federal']);
      DB::table('estados')->insert(['uf' => 'ES', 'descricao' => 'Espírito Santo']);
      DB::table('estados')->insert(['uf' => 'GO', 'descricao' => 'Goiás']);
      DB::table('estados')->insert(['uf' => 'MA', 'descricao' => 'Maranhão']);
      DB::table('estados')->insert(['uf' => 'MG', 'descricao' => 'Minas Gerais']);
      DB::table('estados')->insert(['uf' => 'MS', 'descricao' => 'Mato Grosso do Sul']);
      DB::table('estados')->insert(['uf' => 'MT', 'descricao' => 'Mato Grosso']);
      DB::table('estados')->insert(['uf' => 'PA', 'descricao' => 'Pará']);
      DB::table('estados')->insert(['uf' => 'PB', 'descricao' => 'Paraíba']);
      DB::table('estados')->insert(['uf' => 'PE', 'descricao' => 'Pernambuco']);
      DB::table('estados')->insert(['uf' => 'PI', 'descricao' => 'Piauí']);
      DB::table('estados')->insert(['uf' => 'PR', 'descricao' => 'Paraná']);
      DB::table('estados')->insert(['uf' => 'RJ', 'descricao' => 'Rio de Janeiro']);
      DB::table('estados')->insert(['uf' => 'RN', 'descricao' => 'Rio Grande do Norte']);
      DB::table('estados')->insert(['uf' => 'RO', 'descricao' => 'Rondônia']);
      DB::table('estados')->insert(['uf' => 'RR', 'descricao' => 'Roraima']);
      DB::table('estados')->insert(['uf' => 'RS', 'descricao' => 'Rio Grande do Sul']);
      DB::table('estados')->insert(['uf' => 'SC', 'descricao' => 'Santa Catarina']);
      DB::table('estados')->insert(['uf' => 'SE', 'descricao' => 'Sergipe']);
      DB::table('estados')->insert(['uf' => 'SP', 'descricao' => 'São Paulo']);
      DB::table('estados')->insert(['uf' => 'TO', 'descricao' => 'Tocantins']);
    }
}
