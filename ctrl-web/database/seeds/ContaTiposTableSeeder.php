<?php

use Illuminate\Database\Seeder;
use App\Contatipo;

class ContaTiposTableSeeder extends Seeder
{
  /**
  * Run the database seeds.
  *
  * @return void
  */
	public function run()
	{

		Contatipo::create([
			'id' => '3',
			'grupo_id' => '1',
			'empresa_id' => '1',
			'descricao' => 'Caixa',
			'ativo' => '1',
			'perfil' => '1'
		]);

		Contatipo::create([
			'id' => '2',
			'grupo_id' => '1',
			'empresa_id' => '1',
			'descricao' => 'Aplicação',
			'ativo' => '1',
			'perfil' => '2'
		]);

		Contatipo::create([
			'id' => '1',
			'grupo_id' => '1',
			'empresa_id' => '1',
			'descricao' => 'Conta Corrente',
			'ativo' => '1',
			'perfil' => '1'
		]);

		Contatipo::create([
			'id'=> '4',
			'grupo_id' => '1',
			'empresa_id' => '1',
			'descricao' => 'Antecipação Clientes',
			'ativo' => '1',
			'perfil' => '4'
		]);

		Contatipo::create([
			'id' => '5',
			'grupo_id' => '1',
			'empresa_id' => '1',
			'descricao' => 'Dívidas c/ Banco',
			'ativo' => '1',
			'perfil' => '5'
		]);

		Contatipo::create([
			'id' => '6',
			'grupo_id' => '1',
			'empresa_id' => '1',
			'descricao' => 'Antecipação Fornecedores',
			'ativo' => '1',
			'perfil' => '6'
		]);
		
		Contatipo::create([
			'id' => '7',
			'grupo_id' => '1',
			'empresa_id' => '1',
			'descricao' => 'Empréstimos p/ Terceiros',
			'ativo' => '1',
			'perfil' => '7'
		]);
	
		Contatipo::create([
			'id' => '8',
			'grupo_id' => '1',
			'empresa_id' => '1',
			'descricao' => 'Provisão',
			'ativo' => '1',
			'perfil' => '3'
		]);
	
	}
}
