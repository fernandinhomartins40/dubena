<?php

namespace App\Domain\Apoio;

use App\Models\Apoio\Agencia;
use App\Models\Apoio\Banco;
use App\Models\Apoio\CadastroApoio;
use App\Models\Apoio\Cargo;
use App\Models\Apoio\ClienteContatoSituacao;
use App\Models\Apoio\ClienteContatoTipo;
use App\Models\Apoio\ContaMovimentoTipo;
use App\Models\Apoio\EstadoCivil;
use App\Models\Apoio\Feriado;
use App\Models\Apoio\MotivoNaoVenda;
use App\Models\Apoio\Parentesco;
use App\Models\Apoio\PedidoMotivoAtraso;
use App\Models\Apoio\Profissao;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TelefoneTipo;
use App\Models\Apoio\TipoDocumentoVeiculo;
use App\Models\Apoio\TipoExame;
use App\Models\Apoio\TipoPessoa;
use App\Models\Apoio\Transportadora;

/**
 * Registro dos cadastros de apoio suportados (espelha o legado
 * CadastroApoioController::TIPOS): mapeia o "tipo" da rota para o model,
 * o módulo RBAC e os campos extras com suas regras de validação.
 *
 * Adicionar um cadastro = uma linha aqui + a tabela/model. Sem novo controller.
 */
class CadastroApoioRegistry
{
    /**
     * @var array<string, array{model: class-string<CadastroApoio>, modulo: string, extras: array<string, string>}>
     */
    private const TIPOS = [
        // Clientes
        'segmentos' => ['model' => Segmento::class, 'modulo' => 'cliente', 'extras' => []],
        'tipos-pessoa' => ['model' => TipoPessoa::class, 'modulo' => 'cliente', 'extras' => ['tipopessoacadastro' => 'nullable|string|max:1']],
        'telefone-tipos' => ['model' => TelefoneTipo::class, 'modulo' => 'cliente', 'extras' => ['celular' => 'boolean']],
        'contato-tipos' => ['model' => ClienteContatoTipo::class, 'modulo' => 'cliente', 'extras' => []],
        'contato-situacoes' => ['model' => ClienteContatoSituacao::class, 'modulo' => 'cliente', 'extras' => []],
        // Financeiro
        'bancos' => ['model' => Banco::class, 'modulo' => 'financeiro', 'extras' => ['codigo' => 'nullable|string|max:10', 'site' => 'nullable|string|max:255']],
        'tipos-movimento' => ['model' => ContaMovimentoTipo::class, 'modulo' => 'financeiro', 'extras' => [
            'pagarreceber' => 'nullable|string|max:1',
            'cheque' => 'boolean', 'cartao' => 'boolean', 'valegas' => 'boolean', 'convenio' => 'boolean',
        ]],
        'agencias' => ['model' => Agencia::class, 'modulo' => 'financeiro', 'extras' => [
            'banco_id' => 'nullable|integer|exists:bancos,id',
            'numero' => 'nullable|string|max:20', 'digito' => 'nullable|string|max:2',
        ]],
        // Logística / cadastros gerais
        'transportadoras' => ['model' => Transportadora::class, 'modulo' => 'cliente', 'extras' => [
            'cnpj' => 'nullable|string|max:14', 'telefone' => 'nullable|string|max:20',
        ]],
        'feriados' => ['model' => Feriado::class, 'modulo' => 'empresa', 'extras' => [
            'data' => 'required|date', 'recorrente' => 'boolean',
        ]],
        'profissoes' => ['model' => Profissao::class, 'modulo' => 'cliente', 'extras' => []],
        'estados-civis' => ['model' => EstadoCivil::class, 'modulo' => 'cliente', 'extras' => []],
        // RH (F04) — referenciados pela SPA (config de colaboradores) e antes ausentes.
        'cargos' => ['model' => Cargo::class, 'modulo' => 'colaborador', 'extras' => ['salario_base' => 'nullable|numeric|min:0']],
        'parentescos' => ['model' => Parentesco::class, 'modulo' => 'colaborador', 'extras' => []],
        'tipos-exame' => ['model' => TipoExame::class, 'modulo' => 'colaborador', 'extras' => ['admissional' => 'boolean']],
        // Tipos de documento de VEICULO (T4.5): CRLV, seguro, ANTT. O endpoint
        // `veiculos/{id}/documentos` gravava sem dominio de valores por tras.
        // NAO confundir com o tipo da gestao documental (outro modulo).
        // Motivos de atraso e de nao-venda (T4.8). As colunas ja existiam em
        // `pedidos`, apontando para tabelas que nunca foram criadas.
        'motivos-atraso' => ['model' => PedidoMotivoAtraso::class, 'modulo' => 'pedido', 'extras' => []],
        'motivos-nao-venda' => ['model' => MotivoNaoVenda::class, 'modulo' => 'pedido', 'extras' => []],
        'tipos-documento-veiculo' => ['model' => TipoDocumentoVeiculo::class, 'modulo' => 'veiculo', 'extras' => ['exige_validade' => 'boolean']],
    ];

    public function existe(string $tipo): bool
    {
        return isset(self::TIPOS[CadastroSlugs::canonico($tipo)]);
    }

    /** @return array{model: class-string<CadastroApoio>, modulo: string, extras: array<string, string>} */
    public function config(string $tipo): array
    {
        // Normaliza alias → slug canônico (F00.2) antes de resolver o cadastro,
        // para que /cadastros/{tipo} e /lookups/{tipo} concordem na mesma entidade.
        $tipo = CadastroSlugs::canonico($tipo);
        abort_unless(isset(self::TIPOS[$tipo]), 404, 'Cadastro desconhecido.');

        return self::TIPOS[$tipo];
    }

    /** @return array<int, string> */
    public function tipos(): array
    {
        return array_keys(self::TIPOS);
    }
}
