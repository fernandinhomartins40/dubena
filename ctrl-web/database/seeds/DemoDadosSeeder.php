<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FASE 4 — Seeder de DEMONSTRAÇÃO (dados realistas e volumosos p/ testar telas).
 *
 * Objetivo: deixar o ERP "cheio" para validar manualmente as telas (incl. as que
 * gravam via AJAX, p/ o fechamento do bypass de autorização). NÃO é dado real.
 *
 * Princípios:
 *  - SEM Faker: o deploy roda `composer install --no-dev`, então fakerphp não
 *    existe em produção/staging. Geramos nomes/dados com PHP puro + arrays.
 *  - IDs descobertos em RUNTIME (não hardcoded): reaproveita a base já semeada
 *    pelo DatabaseSeeder (grupo/empresa/cidade/bairro/admin), pegando o 1º de cada.
 *  - IDEMPOTENTE: cada bloco cria só o que falta (firstOrCreate / contadores),
 *    então rodar de novo não duplica nem quebra.
 *  - Cadeia de FKs NOT NULL respeitada (mapeada do information_schema).
 *
 * Volume (ajustável nas constantes): ~50 produtos, ~5000 clientes, ~200 pedidos,
 * ~200 financeiros.
 */
class DemoDadosSeeder extends Seeder
{
    const QTD_PRODUTOS  = 50;
    const QTD_CLIENTES  = 5000;
    const QTD_PEDIDOS   = 200;
    const QTD_FINANCEIRO = 200;

    private $grupoId;
    private $empresaId;
    private $cidadeId;
    private $bairroId;
    private $userId;

    public function run()
    {
        // --- Base: pega o 1º grupo/empresa/cidade/bairro/usuário já existentes. ---
        $this->grupoId   = DB::table('empresas_grupos')->min('id');
        $this->empresaId = DB::table('empresas')->where('grupo_id', $this->grupoId)->min('id') ?? DB::table('empresas')->min('id');
        $this->cidadeId  = DB::table('cidades')->min('id');
        $this->bairroId  = DB::table('bairros')->min('id');
        $this->userId    = DB::table('users')->min('id');

        if (! $this->grupoId || ! $this->empresaId || ! $this->cidadeId || ! $this->bairroId) {
            $this->command->error('Base ausente (grupo/empresa/cidade/bairro). Rode os seeders base antes (DatabaseSeeder).');
            return;
        }

        $uf = DB::table('cidades')->where('id', $this->cidadeId)->value('uf') ?? 'PR';

        $this->command->info("Demo: grupo=$this->grupoId empresa=$this->empresaId cidade=$this->cidadeId bairro=$this->bairroId user=$this->userId");

        $ruaId        = $this->seedRua();
        $tipopessoaId = $this->seedTipopessoa();
        $segmentoId   = $this->seedSegmento();
        $operacaoId   = $this->seedApoioPedido('pedidooperacaos', 'Venda');
        $situacaoId   = $this->seedApoioPedido('pedidosituacaos', 'Aberto');
        $this->seedSetor($ruaId);
        $classeId     = $this->seedProdutoclasse();
        $unidadeId    = $this->seedUnidade();
        $condicaoId   = $this->seedCondicaoPagamento();
        $contaId      = $this->seedContaCaixa();
        $this->seedPlanoCentro();

        $this->seedApoioCadastros(); // tabelas de apoio dos selects (ativo=1)

        $produtoIds = $this->seedProdutos($classeId, $unidadeId);
        $this->seedClientes($uf, $ruaId, $tipopessoaId, $segmentoId);
        $clienteIds = DB::table('clientes')->where('empresa_id', $this->empresaId)->pluck('id')->all();

        $this->seedPedidos($clienteIds, $produtoIds, $ruaId, $operacaoId, $situacaoId, $condicaoId);
        $this->seedFinanceiros($clienteIds);

        $this->command->info('Demo: concluído. Clientes=' . count($clienteIds) . ' Produtos=' . count($produtoIds));
    }

    private function now() { return date('Y-m-d H:i:s'); }

    private function seedRua()
    {
        $rua = DB::table('ruas')->where('empresa_id', $this->empresaId)->first();
        if ($rua) return $rua->id;
        return DB::table('ruas')->insertGetId([
            'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId,
            'cidade_id' => $this->cidadeId, 'descricao' => 'Rua Demonstração',
            'importacaocep_id' => 0, 'nfecompl' => '', 'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
    }

    private function seedTipopessoa()
    {
        // Os selects de cliente filtram por tipopessoacadastro='F' e ativo=1.
        $t = DB::table('tipopessoas')->where('grupo_id', $this->grupoId)->first();
        if ($t) {
            DB::table('tipopessoas')->where('id', $t->id)
                ->update(['tipopessoacadastro' => 'F', 'ativo' => 1]);
            return $t->id;
        }
        return DB::table('tipopessoas')->insertGetId([
            'grupo_id' => $this->grupoId, 'descricao' => 'Pessoa Física',
            'tipopessoacadastro' => 'F', 'ativo' => 1,
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
    }

    private function seedSegmento()
    {
        $s = DB::table('segmentos')->where('grupo_id', $this->grupoId)->first();
        if ($s) {
            DB::table('segmentos')->where('id', $s->id)->update(['ativo' => 1]);
            return $s->id;
        }
        return DB::table('segmentos')->insertGetId([
            'grupo_id' => $this->grupoId, 'descricao' => 'Residencial', 'ativo' => 1,
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
    }

    private function seedApoioPedido($tabela, $descricao)
    {
        $r = DB::table($tabela)->where('empresa_id', $this->empresaId)->first();
        if ($r) return $r->id;
        return DB::table($tabela)->insertGetId([
            'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId,
            'descricao' => $descricao, 'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
    }

    private function seedSetor($ruaId)
    {
        if (DB::table('setors')->where('empresa_id', $this->empresaId)->exists()) {
            DB::table('setors')->where('empresa_id', $this->empresaId)->update(['ativo' => 1]);
            return;
        }
        DB::table('setors')->insert([
            'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId,
            'cidade_id' => $this->cidadeId, 'bairro_id' => $this->bairroId,
            'descricao' => 'Setor Central', 'numero' => '1', 'cep' => '00000000', 'ativo' => 1,
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
    }

    private function seedProdutoclasse()
    {
        $c = DB::table('produtoclasses')->where('grupo_id', $this->grupoId)->first();
        if ($c) return $c->id;
        return DB::table('produtoclasses')->insertGetId([
            'grupo_id' => $this->grupoId, 'descricao' => 'Gás / GLP',
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
    }

    private function seedUnidade()
    {
        $u = DB::table('unidademedidas')->where('grupo_id', $this->grupoId)->first();
        if ($u) return $u->id;
        return DB::table('unidademedidas')->insertGetId([
            'grupo_id' => $this->grupoId, 'descricao' => 'Unidade', 'sigla' => 'UN',
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
    }

    private function seedCondicaoPagamento()
    {
        $c = DB::table('condicaopagamentos')->where('empresa_id', $this->empresaId)->first();
        if ($c) {
            DB::table('condicaopagamentos')->where('id', $c->id)->update(['ativo' => 1]);
            return $c->id;
        }
        return DB::table('condicaopagamentos')->insertGetId([
            'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId,
            'descricao' => 'À Vista', 'tipo' => 0, 'dias_primeira' => 0, 'ativo' => 1,
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
    }

    /**
     * Tabelas de apoio que alimentam os SELECTS dos formulários (cliente, etc.).
     * Todas filtradas por ativo=1 + grupo_id nos repositories — então precisam
     * existir com ativo=1. Idempotente: cria só se não houver no grupo.
     */
    private function seedApoioCadastros()
    {
        $porGrupo = [
            'telefonetipos'           => [['descricao' => 'Celular', 'ativo' => 1, 'celular' => 1], ['descricao' => 'Residencial', 'ativo' => 1, 'celular' => 0], ['descricao' => 'Comercial', 'ativo' => 1, 'celular' => 0]],
            'clientecontatosituacaos' => [['descricao' => 'Pendente', 'ativo' => 1], ['descricao' => 'Resolvido', 'ativo' => 1]],
            'clientecontatotipos'     => [['descricao' => 'Reclamação', 'ativo' => 1], ['descricao' => 'Elogio', 'ativo' => 1], ['descricao' => 'Dúvida', 'ativo' => 1]],
            'estadocivils'            => [['descricao' => 'Solteiro(a)', 'ativo' => 1], ['descricao' => 'Casado(a)', 'ativo' => 1], ['descricao' => 'Divorciado(a)', 'ativo' => 1]],
            'parentescos'             => [['descricao' => 'Cônjuge', 'ativo' => 1], ['descricao' => 'Filho(a)', 'ativo' => 1], ['descricao' => 'Pai/Mãe', 'ativo' => 1]],
        ];

        foreach ($porGrupo as $tabela => $linhas) {
            if (DB::table($tabela)->where('grupo_id', $this->grupoId)->exists()) {
                // Garante ativo=1 nos já existentes (rodadas anteriores podiam não setar).
                DB::table($tabela)->where('grupo_id', $this->grupoId)->update(['ativo' => 1]);
                continue;
            }
            foreach ($linhas as $linha) {
                DB::table($tabela)->insert(array_merge($linha, [
                    'grupo_id' => $this->grupoId,
                    'created_at' => $this->now(), 'updated_at' => $this->now(),
                ]));
            }
        }
    }

    private function seedContaCaixa()
    {
        $existente = DB::table('contas')->where('empresa_id', $this->empresaId)->first();
        if ($existente) return $existente->id;

        $tipoId = DB::table('contatipos')->where('empresa_id', $this->empresaId)->value('id');
        if (! $tipoId) {
            $tipoId = DB::table('contatipos')->insertGetId([
                'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId,
                'descricao' => 'Caixa', 'perfil' => 0, 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
        $contaId = DB::table('contas')->insertGetId([
            'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId, 'contatipo_id' => $tipoId,
            'conta' => 'Caixa Geral', 'descricao' => 'Caixa Geral', 'saldoinicial' => 0, 'saldoatual' => 0,
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        DB::table('contausers')->insert([
            'conta_id' => $contaId, 'user_id' => $this->userId, 'operar' => 1, 'visualizar' => 1,
            'transferir' => 1, 'estornar' => 1, 'lancarfechado' => 1,
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        DB::table('contafechamentos')->insert([
            'conta_id' => $contaId, 'datahoraabertura' => $this->now(), 'saldoinicial' => 0, 'fechado' => 0,
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        return $contaId;
    }

    private function seedPlanoCentro()
    {
        if (! DB::table('planocontas')->where('empresa_id', $this->empresaId)->exists()) {
            foreach ([['R', '001', 'Receita de Vendas'], ['P', '002', 'Despesas Gerais']] as [$pr, $cod, $desc]) {
                DB::table('planocontas')->insert([
                    'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId, 'descricao' => $desc,
                    'insumo_valor' => 0, 'pagarreceber' => $pr, 'codigo' => $cod, 'nivel' => 1,
                    'created_at' => $this->now(), 'updated_at' => $this->now(),
                ]);
            }
        }
        if (! DB::table('centrocustos')->where('empresa_id', $this->empresaId)->exists()) {
            DB::table('centrocustos')->insert([
                'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId, 'descricao' => 'Centro Geral',
                'codigo' => '001', 'nivel' => 1, 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
    }

    private function seedProdutos($classeId, $unidadeId)
    {
        $existentes = DB::table('produtos')->where('empresa_id', $this->empresaId)->pluck('id')->all();
        if (count($existentes) >= self::QTD_PRODUTOS) return $existentes;

        $bases = ['Botijão P13', 'Botijão P45', 'Botijão P20', 'Galão Água 20L', 'Botijão P5',
                  'Recarga P13', 'Recarga P45', 'Kit Mangueira', 'Regulador', 'Adaptador'];
        $linhas = [];
        for ($i = count($existentes); $i < self::QTD_PRODUTOS; $i++) {
            $nome = $bases[$i % count($bases)] . ' #' . ($i + 1);
            $linhas[] = [
                'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId,
                'produtoclasse_id' => $classeId, 'unidademedida_id' => $unidadeId,
                'descricao' => $nome, 'customedio' => round(40 + ($i % 60) + 0.5, 2),
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ];
        }
        foreach (array_chunk($linhas, 200) as $chunk) {
            DB::table('produtos')->insert($chunk);
        }
        return DB::table('produtos')->where('empresa_id', $this->empresaId)->pluck('id')->all();
    }

    private function seedClientes($uf, $ruaId, $tipopessoaId, $segmentoId)
    {
        $jaTem = DB::table('clientes')->where('empresa_id', $this->empresaId)->count();
        if ($jaTem >= self::QTD_CLIENTES) return;

        $nomes = ['Maria', 'José', 'Ana', 'João', 'Paulo', 'Carla', 'Pedro', 'Lucia', 'Marcos', 'Fernanda',
                  'Antônio', 'Beatriz', 'Rafael', 'Juliana', 'Carlos', 'Patrícia', 'Bruno', 'Camila'];
        $sobren = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Pereira', 'Lima', 'Costa', 'Ferreira',
                   'Rodrigues', 'Almeida', 'Nascimento', 'Carvalho', 'Gomes', 'Martins', 'Araújo'];

        $faltam = self::QTD_CLIENTES - $jaTem;
        $linhas = [];
        for ($i = 0; $i < $faltam; $i++) {
            $n = $i + $jaTem;
            $nome = $nomes[$n % count($nomes)] . ' ' . $sobren[($n * 3) % count($sobren)];
            $linhas[] = [
                'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId,
                'nome' => $nome, 'numero' => (string) (10 + ($n % 9000)),
                'cidade_id' => $this->cidadeId, 'bairro_id' => $this->bairroId, 'rua_id' => $ruaId,
                'uf' => $uf, 'cep' => '80000000', 'sexo' => ($n % 2 ? 'M' : 'F'),
                'tipopessoa_id' => $tipopessoaId, 'segmento_id' => $segmentoId, 'user_id' => $this->userId,
                'observacoes' => '', 'conveniolimite' => 0, 'cliente' => 1, 'fornecedor' => 0,
                'transportador' => 0, 'ativo' => 1, 'nfemite' => 0, 'convenio' => 0, 'convenioativo' => 0,
                'latitude' => -25.43 - ($n % 100) / 10000, 'longitude' => -49.27 - ($n % 100) / 10000,
                'locationtype' => 'ROOFTOP', 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ];
            if (count($linhas) >= 500) {
                DB::table('clientes')->insert($linhas);
                $linhas = [];
                $this->command->info('  clientes inseridos: ' . ($n + 1));
            }
        }
        if ($linhas) DB::table('clientes')->insert($linhas);
    }

    private function seedPedidos($clienteIds, $produtoIds, $ruaId, $operacaoId, $situacaoId, $condicaoId)
    {
        if (empty($clienteIds) || empty($produtoIds)) return;
        if (DB::table('pedidos')->where('empresa_id', $this->empresaId)->count() >= self::QTD_PEDIDOS) return;

        for ($i = 0; $i < self::QTD_PEDIDOS; $i++) {
            $clienteId = $clienteIds[$i % count($clienteIds)];
            $produtoId = $produtoIds[$i % count($produtoIds)];
            $qtd = 1 + ($i % 5);
            $preco = round(80 + ($i % 40) + 0.9, 2);
            $total = round($qtd * $preco, 2);

            $pedidoId = DB::table('pedidos')->insertGetId([
                'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId, 'cliente_id' => $clienteId,
                'entregarua_id' => $ruaId, 'entregabairro_id' => $this->bairroId, 'entregacidade_id' => $this->cidadeId,
                'atendenteuser_id' => $this->userId, 'condicaopagamento_id' => $condicaoId,
                'pedidooperacao_id' => $operacaoId, 'pedidosituacao_id' => $situacaoId,
                'datahora' => $this->now(), 'entreganumero' => (string) (10 + $i), 'valorvenda' => $total,
                'latitude' => -25.43, 'longitude' => -49.27, 'entregalatitude' => -25.43, 'entregalongitude' => -49.27,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            DB::table('pedidoitems')->insert([
                'pedido_id' => $pedidoId, 'produto_id' => $produtoId, 'quantidade' => $qtd,
                'precovendaunitario' => $preco, 'precovendatotal' => $total,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
    }

    private function seedFinanceiros($clienteIds)
    {
        if (empty($clienteIds)) return;
        if (DB::table('financeiros')->where('empresa_id', $this->empresaId)->count() >= self::QTD_FINANCEIRO) return;

        for ($i = 0; $i < self::QTD_FINANCEIRO; $i++) {
            $clienteId = $clienteIds[$i % count($clienteIds)];
            $valor = round(100 + ($i % 50) + 0.5, 2);
            $pr = ($i % 4 === 0) ? 'P' : 'R';

            $finId = DB::table('financeiros')->insertGetId([
                'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId, 'cliente_id' => $clienteId,
                'valor' => $valor, 'pagarreceber' => $pr, 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            DB::table('financeiroparcelas')->insert([
                'grupo_id' => $this->grupoId, 'empresa_id' => $this->empresaId, 'financeiro_id' => $finId,
                'numero' => 1, 'datavencimento' => date('Y-m-d', strtotime('+' . ($i % 30) . ' days')),
                'datacompetencia' => date('Y-m-d'), 'valor' => $valor, 'multa' => 0, 'juros' => 0,
                'desconto' => 0, 'valorefetivado' => 0, 'pagarreceber' => $pr,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
        }
    }
}
