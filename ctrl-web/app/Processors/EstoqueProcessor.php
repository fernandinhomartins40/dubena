<?php

namespace App\Processors;

use App\Services\CarbonCustom as Carbon;
use DB;
use Session;
use App\Estoquefechamentosetor;
use App\Estoquefechamento;
use App\Empresaconfig;
use App\Produto;
use App\Setor;
use App\Estoquefisico;
use App\Estoquefisicosetor;
use App\Estoqueproduto;
use App\Estoquesetorhistorico;
use \Exception;
use App\Estoquesetor;

class EstoqueProcessor
{

    protected $errors = Array();
    protected $id_movimento_gerado;
    protected $setoresFechamento;
    protected $produtosFechamento;

    public function addError($error)
    {
        array_push($this->errors, $error);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function setIdMovimentoGerado($id)
    {
        $this->movimento_gerado = $id;
    }

    public function getIdMovimentoGerado()
    {
        return $this->movimento_gerado;
    }

    private function processaEstoquesetorhistorico(array $estoquesetorhistoricos)
    {
        DB::beginTransaction();
        $empresaconfig = Empresaconfig::where('empresa_id', Session::get('empresa_padrao')->id)->first();
        if ($empresaconfig == null)
            throw new Exception("As configurações ainda não foram definidas!");

        foreach ($estoquesetorhistoricos as $estsethist) {
            try {
                $customedioestorno = false;
                if (array_key_exists('customedioestorno', $estsethist->toArray())) {
                    $customedioestorno = is_null($estsethist->customedioestorno) ? 0 : $estsethist->customedioestorno;
                    unset($estsethist->customedioestorno);
                }
                if ($this->isSetorestoqueFechadoData($estsethist->setor_id, $estsethist->datahoracompetencia)) {
                    $msg = 'Setor ' . $estsethist->setor_id . ' - ' .
                            $estsethist->setor->descricao . ' já está fechado na data/hora competência.';
                    throw new Exception($msg);
                }

                $estoquesetor = Estoquesetor::where('produto_id', $estsethist->produto_id)
                                ->where('setor_id', $estsethist->setor_id)->first();
                $negativa = $empresaconfig->permiteestoquenegativo;
                if ($estoquesetor === null) {
                    if ($estsethist->movimentacao == 'ENTRADA' || $negativa) {
                        $estoquesetornew = new Estoquesetor();
                        $estoquesetornew->produto_id = $estsethist->produto_id;
                        $estoquesetornew->setor_id = $estsethist->setor_id;
                        $estoquesetornew->quantidade = 0;
                        $estoquesetornew->quantidademinima = 0;
                        $estoquesetornew->quantidademaxima = 1000;
                        $estoquesetornew->grupo_id = Session::get('empresa_padrao')->grupo_id;
                        $estoquesetornew->empresa_id = Session::get('empresa_padrao')->id;
                        $estoquesetornew->save();
                        $estoquesetor = $estoquesetornew;
                    } else {
                        $msg = 'Setor não tem o produto em seu estoque para movimentar '
                                . 'e a empresa não permite negativar estoque.';
                        throw new Exception($msg);
                    }
                }

                if ($estsethist->movimentacao == 'SAIDA' && $estsethist->quantidade > $estoquesetor->quantidade && !$negativa)
                    throw new Exception('Empresa não permite negativar o estoque.');

                $produto = Produto::find($estsethist->produto_id);

                if ($produto === null) {
                    throw new Exception('Produto não existe cadastrado no sistema', $estsethist->produto_id);
                }

                $estsethist->customedio = $produto->customedio;
                $estsethist->save();

                $this->setIdMovimentoGerado($estsethist->id);

                $qtdeMovimentada = $estsethist->quantidade * ($estsethist->movimentacao == 'SAIDA' ? -1 : 1);

                if ($estoquesetor === null) {
                    $setor = Setor::find($estsethist->setor_id);

                    if ($setor === null) {
                        throw new Exception('Setor não existe cadastrado no sistema', $estsethist->setor_id);
                    }

                    $estoquesetor = new Estoquesetor();
                    $estoquesetor->grupo_id = $estsethist->grupo_id;
                    $estoquesetor->empresa_id = $estsethist->empresa_id;
                    $estoquesetor->produto_id = $estsethist->produto_id;
                    $estoquesetor->setor_id = $estsethist->setor_id;
                    $estoquesetor->quantidade = 0;
                    $estoquesetor->quantidademinima = 0;
                    $estoquesetor->quantidademaxima = 1000;
                    $estoquesetor->save();
                }

                $qtdetotalestoque = Estoquesetor::where("produto_id", $produto->id)->select('quantidade')->get()->sum('quantidade');

                if (is_numeric($customedioestorno)) {
                    if ($qtdetotalestoque === 0 || $produto->customedio == 0)
                        $produto->update(["customedio" => $customedioestorno]);
                    else
                        Produto::updateCustoMedio($produto, $qtdetotalestoque, $estsethist->quantidade, $customedioestorno);
                }
                $estoquesetor->update(array("quantidade" => $estoquesetor->quantidade + $qtdeMovimentada));

                $estoqueproduto = Estoqueproduto::where('produto_id', $estsethist->produto_id)->first();
                if ($estoqueproduto === null) {
                    $estoqueproduto = new Estoqueproduto();
                    $estoqueproduto->grupo_id = $estsethist->grupo_id;
                    $estoqueproduto->empresa_id = $estsethist->empresa_id;
                    $estoqueproduto->produto_id = $estsethist->produto_id;
                    $estoqueproduto->quantidade = 0;
                    $estoqueproduto->quantidademinima = 0;
                    $estoqueproduto->quantidademaxima = 1000;
                    $estoqueproduto->save();
                }
                $estoqueproduto->update(array("quantidade" => $estoqueproduto->quantidade + $qtdeMovimentada));
            } catch (Exception $e) {
                DB::rollback();
                $this->addError($e->getMessage());
                return false;
            }
        }
        DB::commit();
        return true;
    }

    private function isSetorestoqueFechadoData($setor_id, $datahoracompetencia)
    {
        $fechamentossetor = Estoquefechamentosetor::
                        join('estoquefechamentos', 'estoquefechamentos.id', '=', 'estoquefechamentosetors.estoquefechamento_id')
                        ->where('estoquefechamentosetors.setor_id', $setor_id)
                        ->select('estoquefechamentosetors.id', 'reaberto', 'datahorafechamento', 'reabertodatahora')
                        ->whereRaw("estoquefechamentos.datahorafechamento >= TO_DATE('$datahoracompetencia', 'YYYY-MM-DD HH24:MI:SS')")
                        ->where('estoquefechamentos.reaberto', 0)->limit(1)
                        ->get()->first();

        return !is_null($fechamentossetor);
    }

    public function fecharEstoque(Estoquefechamento $estoqueFechamento)
    {
        DB::beginTransaction();
        try {
            $estoquefechamentoapos = Estoquefechamento::where('datahorafechamento', '>=', $estoqueFechamento->datahorafechamento)
                            ->where('empresa_id', $estoqueFechamento->empresa_id)
                            ->where('reaberto', 0)->first();
            if ($estoquefechamentoapos !== null) {
                throw new Exception('Existe um fechamento de estoque após essa data hora fechamento solicitada.');
            } else {
                $ultimoestoquefechamento = Estoquefechamento::where('datahorafechamento', '<=', $estoqueFechamento->datahorafechamento)
                                ->where('empresa_id', $estoqueFechamento->empresa_id)
                                ->where('reaberto', 0)->orderBy('datahorafechamento', 'desc')->first();
                $estoqueFechamento->save();

                $estoquesetorhistoricos = $this->getHistoricoFechamento($ultimoestoquefechamento, $estoqueFechamento);

                if (!count($estoquesetorhistoricos)) {
                    throw new Exception('Não existem movimentação de estoque para realizar um fechamento.');
                }

                $this->setProdutosFechamento($estoquesetorhistoricos->pluck('produto_id'));
                $this->setSetoresFechamento($estoquesetorhistoricos->pluck('setor_id'));

                if (is_null($ultimoestoquefechamento)) {
                    $this->processaHistoricosPrimeiroFechamento($estoquesetorhistoricos, $estoqueFechamento);
                } else {
                    $this->processaHistoricos($estoquesetorhistoricos, $estoqueFechamento, $ultimoestoquefechamento);
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    function getHistoricoFechamento($ultimoFechamento, $estoqueFechamento)
    {
        if (is_null($ultimoFechamento)) {
            $estoquesetorhistoricos = Estoquesetorhistorico::where('datahoracompetencia', '<=', $estoqueFechamento->datahorafechamento)
                            ->where('empresa_id', $estoqueFechamento->empresa_id)
                            ->selectRaw('setor_id, produto_id, movimentacao, sum(quantidade) as quantidade')
                            ->orderBy('setor_id')->orderBy('produto_id')->groupBy('setor_id', 'produto_id', 'movimentacao')->get();
        } else {
            $estoquesetorhistoricos = Estoquesetorhistorico::whereBetween('datahoracompetencia', [$ultimoFechamento->datahorafechamento, $estoqueFechamento->datahorafechamento])
                            ->where('empresa_id', $estoqueFechamento->empresa_id)
                            ->selectRaw('setor_id, produto_id, movimentacao, sum(quantidade) as quantidade')
                            ->orderBy('setor_id')->orderBy('produto_id')->groupBy('setor_id', 'produto_id', 'movimentacao')->get();
            $estoquesetorhistoricos = $this->completaEstoquesetorhistoricosFechamentoAnterior($estoquesetorhistoricos, $estoqueFechamento, $ultimoFechamento);
        }

        return $estoquesetorhistoricos;
    }

    private function completaEstoquesetorhistoricos($estoquesetorhistoricos, $estoqueFechamento)
    {
        $estoquesetors = Estoquesetor::where([['empresa_id', Session::get('empresa_padrao')->id]
                    , ['grupo_id', Session::get('empresa_padrao')->grupo_id]])
                ->orderBy('setor_id', 'produto_id')
                ->get();

        $estoquesetorhistoricosnovo = [];
        foreach ($estoquesetors as $estoquesetor) {
            $jaTem = false;
            foreach ($estoquesetorhistoricos as $estoquesetorhistorico) {
                if ($estoquesetor->setor_id == $estoquesetorhistorico->setor_id && $estoquesetor->produto_id == $estoquesetorhistorico->produto_id) {
                    $jaTem = true;
                    array_push($estoquesetorhistoricosnovo, $estoquesetorhistorico);
                }
            }
        }
        return collect($estoquesetorhistoricosnovo);
    }

    private function completaEstoquesetorhistoricosFechamentoAnterior($estoquesetorhistoricos, $estoqueFechamento, $ultimoestoquefechamento)
    {
        $estoquefechamentosetors = Estoquefechamentosetor::where([['empresa_id', Session::get('empresa_padrao')->id]
                    , ['grupo_id', Session::get('empresa_padrao')->grupo_id]])
                ->where('estoquefechamento_id', $ultimoestoquefechamento->id)
                ->orderBy('setor_id')->orderBy('produto_id')
                ->get();
        
        foreach ($estoquefechamentosetors as $estoquefechamentosetor) {
            $estoqueSetorProd = $estoquesetorhistoricos->filter(function ($hist) use ($estoquefechamentosetor) {
                return $hist->produto_id == $estoquefechamentosetor->produto_id
                        and $hist->setor_id == $estoquefechamentosetor->setor_id;
            });
            $jaTem = $estoqueSetorProd->count() > 0;
            if (!$jaTem) {
                $estoquesetorhistoriconovo = new Estoquesetorhistorico();
                $estoquesetorhistoriconovo->user_id = \Auth::user()->id;
                $estoquesetorhistoriconovo->setor_id = $estoquefechamentosetor->setor_id;
                $estoquesetorhistoriconovo->produto_id = $estoquefechamentosetor->produto_id;
                $estoquesetorhistoriconovo->movimentacao = "MANTEM";
                $estoquesetorhistoriconovo->quantidade = $estoquefechamentosetor->quantidade;
                $estoquesetorhistoriconovo->motivo = "Fechamento de estoque anterior";
                $estoquesetorhistoriconovo->datahora = $estoqueFechamento->datahorafechamento;
                $estoquesetorhistoriconovo->datahoracompetencia = $estoqueFechamento->datahorafechamento;
                $estoquesetorhistoriconovo->entidade = "EstoqueProcessor";
                $estoquesetorhistoriconovo->entidade_id = $estoqueFechamento->id;
                $estoquesetorhistoriconovo->grupo_id = $estoquefechamentosetor->grupo_id;
                $estoquesetorhistoriconovo->empresa_id = $estoquefechamentosetor->empresa_id;
                
                $estoquesetorhistoricos->push($estoquesetorhistoriconovo);
            }
        }
        return $estoquesetorhistoricos;
    }

    private function processaHistoricos($estoquesetorhistoricos, Estoquefechamento $estoqueFechamento, Estoquefechamento $ultimoestoquefechamento)
    {
        $lastsetor_id = 0;
        $lastproduto_id = 0;
        $qtdeMovimentada = 0;
        $qtdeUltimo = 0;

        foreach ($estoquesetorhistoricos as $estoquesetorhistorico) {

            if ($lastsetor_id === 0 && $lastproduto_id === 0 && $qtdeMovimentada === 0) {
                $lastsetor_id = $estoquesetorhistorico->setor_id;
                $lastproduto_id = $estoquesetorhistorico->produto_id;
            }
            if ($lastsetor_id == $estoquesetorhistorico->setor_id) {
                if ($lastproduto_id === $estoquesetorhistorico->produto_id) {
                    if ($qtdeMovimentada == 0)
                        $qtdeMovimentada = $this->buscaQuantidadeMovimentada($ultimoestoquefechamento, $lastsetor_id, $lastproduto_id);
                    $qtdeMovimentada = $this->processaQuantidadeMovimentacao($estoquesetorhistorico, $qtdeMovimentada);
                } else {
                    $this->salvarEstoqueFechamentoSetor($estoqueFechamento, $lastsetor_id, $lastproduto_id, $qtdeMovimentada);

                    $lastproduto_id = $estoquesetorhistorico->produto_id;
                    $qtdeUltimo = $this->buscaQuantidadeMovimentada($ultimoestoquefechamento, $lastsetor_id, $lastproduto_id);
                    $qtdeMovimentada = $this->processaQuantidadeMovimentacao($estoquesetorhistorico, $qtdeUltimo);
                }
            } else {
                $this->salvarEstoqueFechamentoSetor($estoqueFechamento, $lastsetor_id, $lastproduto_id, $qtdeMovimentada);

                $lastsetor_id = $estoquesetorhistorico->setor_id;
                $lastproduto_id = $estoquesetorhistorico->produto_id;
                $qtdeUltimo = $this->buscaQuantidadeMovimentada($ultimoestoquefechamento, $lastsetor_id, $lastproduto_id);
                $qtdeMovimentada = $this->processaQuantidadeMovimentacao($estoquesetorhistorico, $qtdeUltimo);
            }
        }
        $this->salvarEstoqueFechamentoSetor($estoqueFechamento, $lastsetor_id, $lastproduto_id, $qtdeMovimentada);
    }

    private function setProdutosFechamento($ids)
    {
        $this->produtosFechamento = DB::table('produtos p')->whereIn('id', $ids)->get();
    }

    private function setSetoresFechamento($ids)
    {
        $this->setoresFechamento = DB::table('setors s')->whereIn('id', $ids)->get();
    }

    private function processaHistoricosPrimeiroFechamento($estoquesetorhistoricos, Estoquefechamento $estoqueFechamento)
    {
        $lastsetor_id = 0;
        $lastproduto_id = 0;
        $qtdeMovimentada = 0;
        $x = 0;
        foreach ($estoquesetorhistoricos as $estoquesetorhistorico) {
            if ($estoquesetorhistorico->produto_id == 104)
                $x = $x + 1;

            if ($lastsetor_id == 0 && $lastproduto_id == 0 && $qtdeMovimentada == 0) {
                $lastsetor_id = $estoquesetorhistorico->setor_id;
                $lastproduto_id = $estoquesetorhistorico->produto_id;
                $qtdeMovimentada = 0;
            }

            if ($lastsetor_id == $estoquesetorhistorico->setor_id) {
                if ($lastproduto_id == $estoquesetorhistorico->produto_id) {
                    $qtdeMovimentada = $this->processaQuantidadeMovimentacao($estoquesetorhistorico, $qtdeMovimentada);
                } else {
                    $this->salvarEstoqueFechamentoSetor($estoqueFechamento, $lastsetor_id, $lastproduto_id, $qtdeMovimentada);
                    $lastproduto_id = $estoquesetorhistorico->produto_id;
                    $qtdeMovimentada = 0;
                    $qtdeMovimentada = $this->processaQuantidadeMovimentacao($estoquesetorhistorico, $qtdeMovimentada);
                }
            } else {
                $this->salvarEstoqueFechamentoSetor($estoqueFechamento, $lastsetor_id, $lastproduto_id, $qtdeMovimentada);

                $lastsetor_id = $estoquesetorhistorico->setor_id;
                $lastproduto_id = $estoquesetorhistorico->produto_id;
                $qtdeMovimentada = 0;
                $qtdeMovimentada = $this->processaQuantidadeMovimentacao($estoquesetorhistorico, $qtdeMovimentada);
            }
        }
        $this->salvarEstoqueFechamentoSetor($estoqueFechamento, $lastsetor_id, $lastproduto_id, $qtdeMovimentada);
    }

    private function buscaQuantidadeMovimentada($ultimoestoquefechamento, $lastsetor_id, $lastproduto_id)
    {
        if (is_null($ultimoestoquefechamento))
            $ultimoEstoquefechamentosetor = null;
        else
            $ultimoEstoquefechamentosetor = $this->buscaUltimoEstoquefechamentosetor($ultimoestoquefechamento, $lastsetor_id, $lastproduto_id);

        $qtdeMovimentada = 0;
        if (is_null($ultimoEstoquefechamentosetor)) {
            $ultimoEstoquesetor = null;
            if (is_null($ultimoEstoquesetor))
                $qtdeMovimentada = 0;
            else
                $qtdeMovimentada = $ultimoEstoquesetor->quantidade;
        } else {
            $qtdeMovimentada = $ultimoEstoquefechamentosetor->quantidade;
        }
        return $qtdeMovimentada;
    }

    private function buscaUltimoEstoquefechamentosetor(Estoquefechamento $ultimoestoquefechamento, $lastsetor_id, $lastproduto_id)
    {
        $ultimoEstoquefechamentosetor = Estoquefechamentosetor::where('estoquefechamento_id', '=', $ultimoestoquefechamento->id)
                        ->where('setor_id', '=', $lastsetor_id)
                        ->where('produto_id', '=', $lastproduto_id)->first();
        return $ultimoEstoquefechamentosetor;
    }

    private function buscaUltimoEstoquesetor($lastsetor_id, $lastproduto_id)
    {
        $ultimoEstoquesetor = Estoquesetor::where('setor_id', '=', $lastsetor_id)->where('produto_id', '=', $lastproduto_id)->first();
        return $ultimoEstoquesetor;
    }

    private function processaQuantidadeMovimentacao(Estoquesetorhistorico $estoquesetorhistorico, $qtdeMovimentada)
    {
        if ($estoquesetorhistorico->movimentacao == 'ENTRADA')
            $qtdeMovimentada += $estoquesetorhistorico->quantidade;
        else if ($estoquesetorhistorico->movimentacao == 'SAIDA')
            $qtdeMovimentada -= $estoquesetorhistorico->quantidade;
        else if ($estoquesetorhistorico->movimentacao == 'MANTEM')
            $qtdeMovimentada = $estoquesetorhistorico->quantidade;

        return $qtdeMovimentada;
    }

    private function salvarEstoqueFechamentoSetor(Estoquefechamento $estoqueFechamento, $lastsetor_id, $lastproduto_id, $qtde)
    {
        $produto = $this->produtosFechamento->where('id', $lastproduto_id)->first();
        $setor = $this->setoresFechamento->where('id', $lastsetor_id)->first();

        $estoquefechamentosetor = new Estoquefechamentosetor();
        $estoquefechamentosetor->grupo_id = $estoqueFechamento->grupo_id;
        $estoquefechamentosetor->empresa_id = $estoqueFechamento->empresa_id;
        $estoquefechamentosetor->setor_id = $setor->id;
        $estoquefechamentosetor->estoquefechamento_id = $estoqueFechamento->id;
        $estoquefechamentosetor->produto_id = $produto->id;
        $estoquefechamentosetor->quantidade = $qtde;
        if (is_null($produto->customedio))
            $estoquefechamentosetor->customedio = 0;
        else
            $estoquefechamentosetor->customedio = $produto->customedio;

        if (is_null($produto->precovenda))
            $estoquefechamentosetor->precovenda = 0;
        else
            $estoquefechamentosetor->precovenda = $produto->precovenda;

        $estoquefechamentosetor->save();
    }

    public function abrirEstoque(Estoquefechamento $estoquefechamento)
    {
        DB::beginTransaction();
        try {
            $estoquefechamento->datahorafechamento = insertDataOracle($estoquefechamento->datahorafechamento);
            $estoquefechamentos = Estoquefechamento::where('datahorafechamento', '>=', $estoquefechamento->datahorafechamento)
                            ->where('empresa_id', $estoquefechamento->empresa_id)
                            ->where('reaberto', 0)->get();

            if (count($estoquefechamentos) > 0) {
                foreach ($estoquefechamentos as $estoquefechamentoreabrir) {
                    $estoquefechamentoreabrir->reaberto = 1;
                    $estoquefechamentoreabrir->reabertouser_id = $estoquefechamento->reabertouser_id;
                    $estoquefechamentoreabrir->reabertomotivo = $estoquefechamento->reabertomotivo;
                    $estoquefechamentoreabrir->reabertodatahora = Carbon::now();
                    $estoquefechamentoreabrir->update();
                }
            } else {
                throw new Exception('Estoque já está aberto até a data solicitada.');
            }
        } catch (Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    public function efetivarEstoquefisico(Estoquefisico $estoquefisico)
    {
        DB::beginTransaction();
        try {
            if ($estoquefisico->efetivado)
                throw new Exception('Estoque Físico já consta como efetivado id-' . $estoquefisico->id);

            $estoquefisicosetors = Estoquefisicosetor::where('estoquefisico_id', '=', $estoquefisico->id)->get();
            if (count($estoquefisicosetors) > 0) {
                $estoquesetorhistoricos = [];
                foreach ($estoquefisicosetors as $estoquefisicosetor) {
                    $movimentacao = 'ENTRADA';
                    if ($estoquefisicosetor->quantidadesistema > $estoquefisicosetor->quantidadefisica)
                        $movimentacao = 'SAIDA';

                    $qtdeMovimentar = $estoquefisicosetor->quantidadediferenca;
                    if ($estoquefisicosetor->estoquezerar)
                        $qtdeMovimentar = 0;

                    if ($qtdeMovimentar > 0) {
                        $estoquesetorhistorico = new Estoquesetorhistorico();
                        $estoquesetorhistorico->user_id = \Auth::user()->id;
                        $estoquesetorhistorico->setor_id = $estoquefisicosetor->setor_id;
                        $estoquesetorhistorico->produto_id = $estoquefisicosetor->produto_id;
                        $estoquesetorhistorico->movimentacao = $movimentacao;
                        $estoquesetorhistorico->quantidade = $qtdeMovimentar;
                        $estoquesetorhistorico->motivo = 'Efetivado Estoque Físico';
                        $estoquesetorhistorico->datahora = Carbon::now();
                        $estoquesetorhistorico->datahoracompetencia = Carbon::now();
                        $estoquesetorhistorico->entidade = 'Estoquefisico';
                        $estoquesetorhistorico->entidade_id = $estoquefisico->id;
                        $estoquesetorhistorico->grupo_id = $estoquefisico->grupo_id;
                        $estoquesetorhistorico->empresa_id = $estoquefisico->id;
                        array_push($estoquesetorhistoricos, $estoquesetorhistorico);
                    }
                }
                $this->processaEstoquesetorhistorico($estoquesetorhistoricos);
            } else {
                throw new Exception('Não existe Estoque Físico Setor para o Estoque Físico informado id-' . $estoquefisico->id);
            }
            $estoquefisico->efetivado = 1;
            $estoquefisico->update();
        } catch (Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    public function movimentarEstoque($arrayEstoquesetorhistorico)
    {
        DB::beginTransaction();
        try {
            if (count($arrayEstoquesetorhistorico)) {
                if (!$this->processaEstoquesetorhistorico($arrayEstoquesetorhistorico))
                    throw new Exception("Ocorreu um erro ao processar movimentação de estoque.");
            } else {
                throw new Exception('Array de Estoque Setor Histórico veio vazio.');
            }
        } catch (Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

}
