<?php

use Illuminate\Database\Seeder;

class BaseDBSeeder extends Seeder
{

    public function __construct()
    {
        if (! defined("JSON_DIRECTORY")) {
            define("JSON_DIRECTORY", __DIR__ . DIRECTORY_SEPARATOR . "json" . DIRECTORY_SEPARATOR);
        }
    }
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = \App\User::first();
//        ([
//            'name'              => 'jeff',
//            'email'             => 'j.s_almeida@outlook.com',
//            'password'          => app('hash')->make('1234'),
//            'admin'             => true,
//            'erpurl'            => 'localhost/ctrl2',
//            'erpempresa_id'     => 1
//        ]);

        $produtos = $this->produtos($user);
        $this->condicaoPgto($user, $produtos);
        $this->situacoes();
    }

    private function situacoes()
    {
        $situacoes = json_decode(file_get_contents(JSON_DIRECTORY . "situacoes.json"));

        foreach ($situacoes as $situacao) {
            \App\PedidoSituacao::create((array) $situacao);
        }
    }

    private function condicaoPgto($user, $produtos)
    {
        $conds = json_decode(file_get_contents(JSON_DIRECTORY . "condicoesPagamento.json"));
        foreach ($conds as $c) {
            $cond = \App\CondicaoPagamento::create([
                "tipo"      => $c->tipo,
                "descricao" => $c->descricao,
                "ativo"     => true
            ]);
            $condImp = \App\CondicaoPagamentoImportacao::create([
                "erp_id"                => 1,
                "user_id"               => $user->id,
                "condicaopagamento_id"  => $cond->id,
                "ativo"                 => true
            ]);
             $this->prodCondPgto($produtos, $condImp->id);
        }
    }

    private function prodCondPgto($produtos, $cond_id)
    {
        $i = 0;
        foreach ($produtos as $prod) {
            \App\ProdutoCondicaoPagamento::create([
                "valor"                             => ($i === 0 ? 245.0 : 65.00),
                "condicaopagamentoimportacao_id"    => $cond_id,
                "produtoimportacao_id"              => $prod->id,
            ]);
            $i++;
        }
    }

    private function produtos($user)
    {
        $cat = \App\ProdutoCategoria::create([
            "descricao" => "GLP",
            "ativo"     => true,
        ]);

        $produtos = json_decode(file_get_contents(JSON_DIRECTORY . "produtos.json"));
        $returns = collect([]);
        foreach ($produtos as $p) {
            $prod = \App\Produto::create([
                "descricao"             => $p->descricao,
                "produtocategoria_id"   => $cat->id,
                "ativo"                 => true,
            ]);
            $returns->push(\App\ProdutoImportacao::create([
                "produtocategoriaimportacao_id" => null,
                "erp_id"                        => 5,
                "user_id"                       => $user->id,
                "produto_id"                    => $prod->id
            ]));
        }
        return $returns;
    }
}
