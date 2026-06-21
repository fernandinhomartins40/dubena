<?php

namespace App\Domain\Apoio;

use App\Models\Apoio\Banco;
use App\Models\Apoio\CadastroApoio;
use App\Models\Apoio\ClienteContatoSituacao;
use App\Models\Apoio\ClienteContatoTipo;
use App\Models\Apoio\ContaMovimentoTipo;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TelefoneTipo;
use App\Models\Apoio\TipoPessoa;

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
    ];

    public function existe(string $tipo): bool
    {
        return isset(self::TIPOS[$tipo]);
    }

    /** @return array{model: class-string<CadastroApoio>, modulo: string, extras: array<string, string>} */
    public function config(string $tipo): array
    {
        abort_unless($this->existe($tipo), 404, 'Cadastro desconhecido.');

        return self::TIPOS[$tipo];
    }

    /** @return array<int, string> */
    public function tipos(): array
    {
        return array_keys(self::TIPOS);
    }
}
