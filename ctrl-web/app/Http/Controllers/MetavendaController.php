<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Setor;
use App\Metavenda;
use App\Produto;
use App\Http\Requests;
use Illuminate\Http\Request;

class MetavendaController extends Controller
{

    public function index(Metavenda $met)
    {
        $this->authorize('view',$met);
        $setor = '';
        $setores = Setor::where([
                    ['ativo', 1],
                    ['empresa_id', Session::get('empresa_padrao')->id]
                ])->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $produtos = Produto::where('ativo', 1)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao') ->pluck('descricao', 'id')->prepend('', '');
        $produto = '';

        $metavendas = Metavenda::where('empresa_id', Session::get('empresa_padrao')->id);

        if(isset($_GET['setor_id']) && !empty($_GET['setor_id']) && $_GET['setor_id'] != null && $_GET['setor_id'] !== 'null') {
            $metavendas = $metavendas->where('setor_id', $_GET['setor_id']);
            $setor = $_GET['setor_id'];

            if(isset($_GET['produto_id']) && !empty($_GET['produto_id']) && $_GET['produto_id'] != null && $_GET['produto_id'] !== 'null') {
                $metavendas = $metavendas->where('produto_id', $_GET['produto_id']);        
                $produto = $_GET['produto_id'];
            }
        }
        if(isset($_GET['data']) && !empty($_GET['data']))
                // FASE 1 (segurança — S1/SQLi): antes interpolava $_GET['data']
                // direto no whereRaw. Agora usa binding parametrizado.
                $metavendas = $metavendas->whereRaw("TO_CHAR(datameta, 'mm/yyyy') = ?", [$_GET['data']]);

        $metavendas = $metavendas->get();
        foreach ($metavendas as $value) {
            $mes = explode(' ', $value->datameta);
            $mes = explode('-', $mes[0]);
            $mes[1] = $this->verificaMes($mes[1]);
            $value->datameta = $mes[1] . '/' . $mes[0];
        }
        return View('metavendas.metavendas', compact('metavendas', 'setores', 'setor', 'produtos', 'produto'));
    }

    public function store(Request $request, Metavenda $met)
    {
        $this->authorize('create',$met);
        $data = $request->all();
        DB::beginTransaction();
        try {
            $metaVendas = Metavenda::where([
                        ['setors.empresa_id', Session::get('empresa_padrao')->id],
                        ['datameta', insertDataOracle($data['mesAtual'])],
                        ['setor_id', $data['formsetor_id']],
                        ['produto_id', $data['formproduto_id']],
                        ['setors.ativo', 1],
                        ['produtos.ativo', 1],
                    ])->join('setors', 'metavendas.setor_id', '=', 'setors.id')
                    ->join('produtos', 'metavendas.produto_id', '=', 'produtos.id')
                    ->get();
            if (count($metaVendas) > 0) {
                DB::rollback();
                return 'OPS';
            }
            $metaVenda = new Metavenda();
            $metaVenda->setor_id = $data['formsetor_id'];
            $metaVenda->produto_id = $data['formproduto_id'];
            $metaVenda->causa = 0;
            $metaVenda->acao = 0;
            $metaVenda->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $metaVenda->empresa_id = Session::get('empresa_padrao')->id;
            $metaVenda->datameta = insertDataOracle($data['mesAtual']);
            $metaVenda->quantidade = $data['quantidade'] !== '' ? $data['quantidade'] : 0;
            $metaVenda->valormeta = $data['valor'] !== '' ? insertNumeroDecimalOracle($data['valor']) : 0;
            $metaVenda->quantidadedesafio = $data['quantidadedesafio'] !== '' ? $data['quantidadedesafio'] : 0;
            $metaVenda->valordesafio = $data['valordesafio'] !== '' ? insertNumeroDecimalOracle($data['valordesafio']) : 0;
            $metaVenda->quantidadeperfil = $data['quantidadeperfil'] !== '' ? $data['quantidadeperfil'] : 0;
            $metaVenda->valorperfil = $data['valorperfil'] !== '' ? insertNumeroDecimalOracle($data['valorperfil']) : 0;
            $metaVenda->quantidadevalegas = $data['quantidadevalegas'] !== '' ? $data['quantidadevalegas'] : 0;
            $metaVenda->valorvalegas = $data['valorvalegas'] !== '' ? insertNumeroDecimalOracle($data['valorvalegas']) : 0;
            $metaVenda->quantidadeconvenio = $data['quantidadeconvenio'] !== '' ? $data['quantidadeconvenio'] : 0;
            $metaVenda->valorconvenio = $data['valorconvenio'] !== '' ? insertNumeroDecimalOracle($data['valorconvenio']) : 0;
            $metaVenda->save();
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollback();
            return $e->getMessage();
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        $mes = explode(' ', $metaVenda->datameta);
        $mes = explode('-', $mes[0]);
        $mes[1] = $this->verificaMes($mes[1]);
        $metaVenda->datameta = $mes[1] . '/' . $mes[0];
        $conteudoTable = '<tr><td class="active">' . $metaVenda->datameta . '</td>'
                . '<td>' . $metaVenda->produto->descricao . '</td>'
                . '<td>' . $metaVenda->setor->descricao . '</td>'
                . '<td>' . $metaVenda->quantidade . '</td>'
                . '<td>' . requestNumeroDecimalOracle($metaVenda->valormeta) . '</td>'
                . '<td>'
                . ''
                . '</td></tr>';
        return 'OK|' . $conteudoTable;
    }

    function update(Request $request, $id, Metavenda $met)
    {
        $this->authorize('update',$met);
        $data = $request->only('valor', 'quantidade', 'valorperfil', 'quantidadeperfil', 
                'valordesafio', 'quantidadedesafio', 'valorvalegas', 'quantidadevalegas', 'valorconvenio', 'quantidadeconvenio');
        DB::beginTransaction();
        try {
            $metaVenda = Metavenda::findOrFail($id);
            $this->authorize('igualdade',$metaVenda);
            $data['quantidade'] = $data['quantidade'] !== '' ? $data['quantidade'] : 0;
            $data['valormeta'] = $data['valor'] !== '' ? insertNumeroDecimalOracle($data['valor']) : 0;
            $data['quantidadedesafio'] = $data['quantidadedesafio'] !== '' ? $data['quantidadedesafio'] : 0;
            $data['valordesafio'] = $data['valordesafio'] !== '' ? insertNumeroDecimalOracle($data['valordesafio']) : 0;
            $data['quantidadeperfil'] = $data['quantidadeperfil'] !== '' ? $data['quantidadeperfil'] : 0;
            $data['valorperfil'] = $data['valorperfil'] !== '' ? insertNumeroDecimalOracle($data['valorperfil']) : 0;
            $data['quantidadeperfil'] = $data['quantidadeperfil'] !== '' ? $data['quantidadeperfil'] : 0;
            $data['valorvalegas'] = $data['valorvalegas'] !== '' ? insertNumeroDecimalOracle($data['valorvalegas']) : 0;
            $data['quantidadevalegas'] = $data['quantidadevalegas'] !== '' ? $data['quantidadevalegas'] : 0;
            $data['valorconvenio'] = $data['valorconvenio'] !== '' ? insertNumeroDecimalOracle($data['valorconvenio']) : 0;
            $data['quantidadeconvenio'] = $data['quantidadeconvenio'] !== '' ? $data['quantidadeconvenio'] : 0;
            $metaVenda->update($data);
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollback();
            return $e->getMessage();
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        return 'EDIT|';
    }

    function verificaMes($mes)
    {
        switch ($mes) {
            case 1:
                return 'Jan';
                break;
            case 2:
                return 'Fev';
                break;
            case 3:
                return 'Mar';
                break;
            case 4:
                return 'Abr';
                break;
            case 5:
                return 'Maio';
                break;
            case 6:
                return 'Jun';
                break;
            case 7:
                return 'Jul';
                break;
            case 8:
                return 'Ago';
                break;
            case 9:
                return 'Set';
                break;
            case 10:
                return 'Out';
                break;
            case 11:
                return 'Nov';
                break;
            default:
                return 'Dez';
                break;
        }
    }

}
