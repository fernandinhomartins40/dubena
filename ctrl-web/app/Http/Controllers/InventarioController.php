<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Exception;
use App\Produto;
use App\Services\CarbonCustom as Carbon;
use App\Inventario;
use App\Inventarioitems;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

class InventarioController extends Controller
{

    protected $errors;

    public function __construct() {
        $this->errors = [];
    }

    protected function addError($error)
    {
        return array_push($this->errors,$error);
    }

    protected function getErrors()
    {
        return implode($this->errors,', ');
    }

    public function index(Inventario $inv)
    {
        $this->authorize('view',$inv);
        $empresa_id = Session::get('empresa_padrao')->id;
        $inventario = Inventario::where('empresa_id',$empresa_id)
            ->selectRaw("id, valorinventario, to_char(mesentrega,'mm/yyyy') as mesentrega, ".
                "to_char(datainventario,'dd/mm/yyyy') as descricao")
            ->get();
        return view('speds.inventario.index')->with(compact('inventario'));
    }

    public function create(Inventario $inv)
    {
        $this->authorize('create',$inv);
        $empresa_id = Session::get('empresa_padrao')->id;
        $produtos = Produto::where(['empresa_id' => $empresa_id, 'ativo' => 1, 'nfepermite' => 1])
            ->orderBy('descricao')->pluck('descricao','id')->prepend('Selecione','');
        return view('speds.inventario.inventario_form')->with(compact('produtos'));
    }
    
    public function store(Request $request, Inventario $inv)
    {
        $this->authorize('create',$inv);
        $empresa_id = Session::get('empresa_padrao')->id;
        $data = $request->all();
        $produtos = json_decode($data["produtos"]);
        $total = $produtos->total;
        unset($produtos->total);
        $extras = $this->dadosExtras($data, $total);

        DB::beginTransaction();
        try{
            $inventario = Inventario::create($extras);
            if( isset($inventario->id) ) $inserido = $this->criarItems($produtos,$inventario);
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError("Error: " . $e->getMessage() . " Line: " . $e->getLine());
            return redirect('inventario/create')->withErrors($this->getErrors())->withInput();
        }
        if(!isset($inserido) && !$inserido) {
            DB::rollback();
            return redirect('inventario/create')->withErrors($this->getErrors())->withInput();
        }
        DB::commit();
        return redirect('inventario')->withMessageSuccess("Inventário gravado com sucesso!");
    }

    public function show($id, Inventario $inv)
    {
        $this->authorize('view',$inv);
        $empresa_id = Session::get('empresa_padrao')->id;
        $show = true;
        $inventario = Inventario::where('id',$id)
            ->selectRaw("id, valorinventario, empresa_id, to_char(mesentrega,'mm/yyyy') as mesentrega, ".
                "to_char(datainventario,'dd/mm/yyyy') as datainventario")
            ->get()->first();

        $this->authorize('igualdade',$inventario);
        $items = DB::table('Inventarioitems as items')->join('produtos','items.produto_id','produtos.id')
            ->where('inventario_id',$id)
            ->select('produtos.descricao','items.produto_id','items.valorunitario','items.quantidade',
                DB::raw('(items.valorunitario * items.quantidade) as total'))
            ->get();

        $produtos = Produto::where(['empresa_id' => $empresa_id, 'ativo' => 1, 'nfepermite' => 1])
            ->orderBy('descricao')->pluck('descricao','id')->prepend('Selecione','');

        return view('speds.inventario.inventario_form')->with(compact('inventario','items','produtos','show'));
    }

    public function edit($id, Inventario $inv)
    {
        $this->authorize('update',$inv);
        $empresa_id = Session::get('empresa_padrao')->id;
        $inventario = Inventario::where('id',$id)
            ->select('id','valorinventario',DB::raw("to_char(mesentrega,'mm/yyyy') as mesentrega, ".
                "to_char(datainventario,'dd/mm/yyyy') as datainventario, empresa_id"))
            ->get()->first();
        $this->authorize('igualdade',$inventario);
        $items = DB::table('Inventarioitems as items')->join('produtos','items.produto_id','produtos.id')
            ->where('inventario_id',$id)
            ->select('produtos.descricao','items.produto_id','items.valorunitario','items.quantidade',
                DB::raw('(items.valorunitario * items.quantidade) as total'))
            ->get();

        $produtos = Produto::where(['empresa_id' => $empresa_id, 'ativo' => 1, 'nfepermite' => 1])
            ->orderBy('descricao')->pluck('descricao','id')->prepend('Selecione','');

        return view('speds.inventario.inventario_form')->with(compact('inventario','items','produtos'));
    }

    public function update(Request $request, $id, Inventario $inv)
    {
        $this->authorize('update',$inv);
        $empresa_id = Session::get('empresa_padrao')->id;
        $inventario = Inventario::find($id);
        $this->authorize('igualdade',$inventario);
        $data = $request->all();
        $produtos = json_decode($data["produtos"]);
        $total = $produtos->total;
        unset($produtos->total);
        $extras = $this->dadosExtras($data, $total);

        DB::beginTransaction();
        try{
            $inventario->items()->delete();
            $up = $inventario->update($extras);
            if($up) $criado = $this->criarItems($produtos, $inventario);
        } catch ( Exception $e ) {
            DB::rollback();
            $this->addError("Error: " . $e->getMessage() . " Line: " . $e->getLine());
            return redirect('inventario/'.$id.'/edit/')->withErrors($this->getErrors())->withInput();
        }
        DB::commit();
        if(!isset($criado) && !$criado) {
            return redirect('inventario/create')->withErrors($this->getErrors())->withInput();
        }
        return redirect('inventario')->withMessageSuccess("Inventário atualizado com sucesso!");
    }

    public function destroy($id, Inventario $inv)
    {
        $this->authorize('delete',$inv);
        $inventario = Inventario::find($id);
        $this->authorize('igualdade',$inventario);
        DB::beginTransaction();
        try {
            $inventario->delete();
        } catch ( \Exception $e ) {
            DB::rollback();
            return "<br /> Error: " . $e->getMessage() . " Line: " . $e->getLine();
        }
        DB::commit();
        return "OK|";
    }

    private function dadosExtras($data,$total)
    {
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["datainventario"] = insertDataOracle($data["datainventario"]);
        $data["mesentrega"] = insertDataOracle('01/'.$data["mesentrega"]);
        $data["valorinventario"] = $total;
        return $data;
    }

    private function criarItems($produtos,$inventario)
    {
        DB::beginTransaction();
        try{
            $inventarioitems = collect([]);
            foreach ($produtos->produtos as $produto) {
                $items = new Inventarioitems();
                $items->inventario_id = $inventario->id;
                $items->produto_id = $produto->produto_id;
                $items->quantidade = $produto->quantidade;
                $items->valorunitario = $produto->valorunitario;
                $items->created_at = Carbon::now()->format('Y-m-d H:m:s');
                $items->updated_at = Carbon::now()->format('Y-m-d H:m:s');
                $inventarioitems->push($items);
            }
            Inventarioitems::insert($inventarioitems->toArray());
        } catch (Exception $e) {
            DB::rollback();
            $this->addError("Error: " . $e->getMessage() . " Line: " . $e->getLine());
            return false;
        }
        DB::commit();
        return true;
    }

}
