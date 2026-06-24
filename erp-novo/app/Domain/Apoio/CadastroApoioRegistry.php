<?php

namespace App\Domain\Apoio;

use App\Models\Apoio\Agencia;
use App\Models\Apoio\Banco;
use App\Models\Apoio\CadastroApoio;
use App\Models\Apoio\ClienteContatoSituacao;
use App\Models\Apoio\ClienteContatoTipo;
use App\Models\Apoio\ContaMovimentoTipo;
use App\Models\Apoio\EstadoCivil;
use App\Models\Apoio\Feriado;
use App\Models\Apoio\Profissao;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TelefoneTipo;
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
