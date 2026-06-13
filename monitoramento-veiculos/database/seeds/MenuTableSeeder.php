
<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Menu;
use App\User;

class MenuTableSeeder extends Seeder
{

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{

		$menus = Menu::where('parent_id', '!=', null)->orderBy('parent_id', 'desc')->get();
		foreach ($menus as $menu) {
			if($menu->users() !== null) {
				$menu->users()->detach();
			}
			$menu->delete();
		}

		$menus = Menu::all();
		foreach ($menus as $menu) {
			$menu->delete();
		}

		//cadastros **MESTRE
		$this->checaIds(['id' => 1, 'parent_id' => NULL, 'titulo' => 'Cadastros', 'descricao' => '', 'ordem' => 10]);
		$this->submenuCadastro();

	   //operações **PAI
		$this->checaIds(['id' => 2, 'parent_id' => NULL, 'titulo' => 'Operações', 'descricao' => '', 'ordem' => 450]);
		$this->submenuOperacoes();

		$this->checaIds(['id' => 5, 'parent_id' => NULL, 'titulo' => 'Relatórios', 'descricao' => '', 'ordem' => 460]);
		$this->submenuRelatorios();

		$users = User::all();  
		foreach ($users as $user) {
			$menus = Menu::all();
			foreach ($menus as $menu) {
				DB::table('menu_user')->insert(['menu_id' => $menu->id, 'user_id' => $user->id]);   
				$menu->ordem = $menu->id;
				$menu->save(); 
			}  
		}
	}

	//cadastro id => 1
	private function submenuCadastro()
	{
		//Administração **PAI
		$this->checaIds(['id' => 10, 'parent_id' => 1, 'titulo' => 'Administração', 'descricao' => '', 'ordem' => 101]);
                $this->checaIds(['id' => 11, 'parent_id' => 1, 'titulo' => 'Cercas Eletrônicas', 'descricao' => 'cerca.index', 'ordem' => 110]);
		$this->submenuAdministracao();
	}

	//Operacoes id => 2
	private function submenuOperacoes()
	{
		$this->checaIds(['id' => 21, 'parent_id' => 2, 'titulo' => 'Rastreamento', 'descricao' => 'rastreamento.index', 'ordem' => 460]);
	}

	//ferramentas id => 5
	private function submenuRelatorios()
	{
		//relatorios 1 **PAI
		$this->checaIds(['id' => 501, 'parent_id' => 5, 'titulo' => 'Rotas por Veículos', 'descricao' => 'rota.index', 'ordem' => 50]);
                $this->checaIds(['id' => 502, 'parent_id' => 5, 'titulo' => 'Eventos por Veículos', 'descricao' => 'evento.index', 'ordem' => 51]);
	}

	//filhos ADMINISRAÇÃO id => 10
	private function submenuAdministracao()
	{
		$this->checaIds(['id' => 101, 'parent_id' => 10, 'titulo' => 'Empresas', 'descricao' => 'empresa.index', 'ordem' => 385]);
		$this->checaIds(['id' => 102, 'parent_id' => 10, 'titulo' => 'Grupos de Empresas', 'descricao' => 'empresas_grupo.index', 'ordem' => 390]);
		$this->checaIds(['id' => 104, 'parent_id' => 10, 'titulo' => 'Usuários', 'descricao' => 'user.index', 'ordem' => 170]);
		$this->checaIds(['id' => 105, 'parent_id' => 10, 'titulo' => 'Veículos', 'descricao' => 'veiculo.index', 'ordem' => 175]);
		$this->checaIds(['id' => 106, 'parent_id' => 10, 'titulo' => 'Criar Nova Empresa', 'descricao' => 'cadastro.indexnew', 'ordem' => 180]);
		$this->checaIds(['id' => 107, 'parent_id' => 10, 'titulo' => 'Atualizar Cadastros', 'descricao' => 'cadastro.index', 'ordem' => 190]);
		$this->checaIds(['id' => 108, 'parent_id' => 10, 'titulo' => 'Configuração Geral', 'descricao' => 'config.index', 'ordem' => 200]);
	}

	private function checaIds($attr)
	{
		$row = Menu::find($attr['id']);
		$menu = new Menu();
		if ($row === null) {
			$menu->exists = false;
			return Menu::create($attr);
		} else {
			$menu->exists = true;
			return $menu->update($attr);
		}
	}

}
