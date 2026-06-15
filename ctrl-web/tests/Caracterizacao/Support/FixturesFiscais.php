<?php

namespace Tests\Caracterizacao\Support;

use DB;
use Session;
use App\Estado;
use App\Cidade;
use App\Bairro;
use App\EmpresasGrupo;
use App\Empresa;
use App\Setor;
use App\Empresaconfig;
use App\Produtoclasse;
use App\Unidademedida;
use App\Produto;
use App\Cliente;
use App\Planoconta;
use App\Centrocusto;

/**
 * Cria o cenário MÍNIMO sintético para exercitar os motores (estoque/financeiro)
 * em testes de caracterização — sem dados reais. Toda a cadeia de FKs NOT NULL:
 *
 *   estado → cidade → bairro → grupo → empresa → setor → empresaconfig
 *                                         └→ produtoclasse → unidademedida → produto
 *
 * Use com o trait DatabaseTransactions no TestCase para reverter tudo ao fim.
 * Popula a Session 'empresa_padrao' como em produção (os processors leem dela).
 */
trait FixturesFiscais
{
    /** @var Empresa */
    protected $empresa;
    /** @var Setor */
    protected $setor;
    /** @var Produto */
    protected $produto;

    /**
     * Monta o cenário e seta a Session. $configOverrides permite ajustar a
     * empresaconfig (ex.: ['permiteestoquenegativo' => 1]).
     */
    /**
     * Alinha a sequence serial de uma tabela ao max(id) atual. O DB de dev/CI
     * tem registros semeados com id explícito sem avançar a sequence — sem isso
     * o próximo insert do Eloquent colide com um id já existente.
     */
    private function sincronizarSequence($tabela)
    {
        DB::statement(
            "SELECT setval(pg_get_serial_sequence('{$tabela}', 'id'), " .
            "COALESCE((SELECT MAX(id) FROM {$tabela}), 0) + 1, false)"
        );
    }

    protected function criarCenarioFiscal(array $configOverrides = [], array $produtoOverrides = [])
    {
        foreach ([
            'cidades', 'bairros', 'empresas_grupos', 'empresas', 'setors',
            'empresaconfigs', 'produtoclasses', 'unidademedidas', 'produtos',
            'estoquesetors', 'estoqueprodutos', 'estoquesetorhistoricos',
        ] as $tabela) {
            $this->sincronizarSequence($tabela);
        }

        // 'estados' tem PK em uf (não incrementing) e uf não é fillable — setar
        // direto. uf fictícia 'ZZ' p/ não colidir com seeds; cria só se faltar.
        $estado = Estado::find('ZZ');
        if ($estado === null) {
            $estado = new Estado();
            $estado->uf = 'ZZ';
            $estado->descricao = 'Estado Teste';
            $estado->save();
        }

        $cidade = new Cidade();
        $cidade->descricao = 'Cidade Teste';
        $cidade->uf = 'ZZ';
        $cidade->save();

        $grupo = new EmpresasGrupo();
        $grupo->descricao = 'Grupo Teste';
        $grupo->save();

        $bairro = new Bairro();
        $bairro->grupo_id = $grupo->id;
        $bairro->cidade_id = $cidade->id;
        $bairro->descricao = 'Bairro Teste';
        $bairro->save();

        $empresa = new Empresa();
        $empresa->grupo_id = $grupo->id;
        $empresa->razao_social = 'Empresa Teste LTDA';
        $empresa->ativo = true;
        $empresa->cidade_id = $cidade->id;
        $empresa->bairro_id = $bairro->id;
        $empresa->cep = '00000000';
        $empresa->uf = 'ZZ';
        $empresa->save();

        $setor = new Setor();
        $setor->grupo_id = $grupo->id;
        $setor->empresa_id = $empresa->id;
        $setor->cidade_id = $cidade->id;
        $setor->bairro_id = $bairro->id;
        $setor->descricao = 'Setor Teste';
        $setor->numero = '1';
        $setor->cep = '00000000';
        $setor->save();

        $config = new Empresaconfig();
        $config->grupo_id = $grupo->id;
        $config->empresa_id = $empresa->id;
        $config->diastrabalhadosemana = 6;
        $config->setorprincipal_id = $setor->id;
        $config->permiteestoquenegativo = 0;
        foreach ($configOverrides as $k => $v) {
            $config->{$k} = $v;
        }
        $config->save();

        $classe = new Produtoclasse();
        $classe->grupo_id = $grupo->id;
        $classe->descricao = 'Classe Teste';
        $classe->save();

        $unidade = new Unidademedida();
        $unidade->grupo_id = $grupo->id;
        $unidade->descricao = 'Unidade';
        $unidade->sigla = 'UN';
        $unidade->save();

        // public.produtos exige: grupo_id, empresa_id, produtoclasse_id,
        // unidademedida_id, descricao. customedio é nullable (motor o usa).
        $produto = new Produto();
        $produto->grupo_id = $grupo->id;
        $produto->empresa_id = $empresa->id;
        $produto->produtoclasse_id = $classe->id;
        $produto->unidademedida_id = $unidade->id;
        $produto->descricao = 'Produto Teste';
        $produto->customedio = 0;
        foreach ($produtoOverrides as $k => $v) {
            $produto->{$k} = $v;
        }
        $produto->save();

        $this->empresa = $empresa;
        $this->setor = $setor;
        $this->produto = $produto;

        // Os processors leem Session::get('empresa_padrao')->id / ->grupo_id.
        Session::put('empresa_padrao', $empresa);

        return $empresa;
    }

    /** Cliente mínimo para o cenário atual (requer criarCenarioFiscal antes). */
    protected function criarCliente()
    {
        $c = new Cliente();
        $c->grupo_id = $this->empresa->grupo_id;
        $c->empresa_id = $this->empresa->id;
        $c->nome = 'Cliente Teste';
        $c->numero = '1';
        $c->cidade_id = $this->empresa->cidade_id;
        $c->conveniolimite = 0;
        $c->latitude = 0;
        $c->longitude = 0;
        $c->locationtype = 'APPROXIMATE';
        $c->save();
        return $c;
    }

    /** Plano de conta finalizador (codigo 3 dígitos, nivel 1). */
    protected function criarPlanoconta($pagarreceber = 'R', $codigo = '001')
    {
        $p = new Planoconta();
        $p->grupo_id = $this->empresa->grupo_id;
        $p->empresa_id = $this->empresa->id;
        $p->descricao = 'Plano Teste ' . $codigo;
        $p->insumo_valor = 0;
        $p->pagarreceber = $pagarreceber;
        $p->codigo = $codigo;
        $p->nivel = 1;
        $p->save();
        return $p;
    }

    /** Centro de custo finalizador (codigo 3 dígitos, nivel 1). */
    protected function criarCentrocusto($codigo = '001')
    {
        $cc = new Centrocusto();
        $cc->grupo_id = $this->empresa->grupo_id;
        $cc->empresa_id = $this->empresa->id;
        $cc->descricao = 'Centro Teste ' . $codigo;
        $cc->codigo = $codigo;
        $cc->nivel = 1;
        $cc->save();
        return $cc;
    }
}
