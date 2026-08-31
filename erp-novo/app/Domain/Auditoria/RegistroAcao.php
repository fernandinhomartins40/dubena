<?php

namespace App\Domain\Auditoria;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registro de ação SEMÂNTICA na trilha.
 *
 * O trait `Auditavel` cobre o CRUD automático (created/updated/deleted) — é o
 * "o quê mudou". Este serviço cobre o "o quê foi DECIDIDO": desativar, estornar,
 * aprovar, recusar. Sem ele, desativar um cliente e corrigir o CEP dele geram a
 * mesma linha `clientes/atualizado`, e a pergunta que motivou a auditoria
 * ("quem tirou este cliente da lista, e por quê?") fica sem resposta.
 *
 * A ação semântica é gravada ADICIONALMENTE ao update automático: o update diz
 * quais colunas mudaram, esta diz o que a pessoa quis fazer.
 */
class RegistroAcao
{
    /**
     * Grava uma ação sobre um model.
     *
     * @param  string  $acao  chave de CatalogoAuditoria::ACOES
     * @param  string|null  $motivo  o "porquê" digitado pela pessoa
     * @param  array<string,mixed>  $contexto  dados extras exibidos na trilha
     */
    public function registrar(Model $alvo, string $acao, ?string $motivo = null, array $contexto = []): void
    {
        // ABSORVE o `atualizado` que o trait acabou de gravar para este mesmo
        // registro, em vez de somar uma segunda linha.
        //
        // Sem isto, desativar um cliente produz DUAS entradas na linha do tempo
        // — "Desativou" e um "Alterou (4 campos)" logo abaixo, do mesmo segundo
        // e do mesmo autor. Para quem lê a trilha isso parece duas ações, e o
        // ruído esconde a decisão. Uma ação humana = uma linha, com o diff junto.
        $automatico = AuditLog::query()
            ->where('entidade', $alvo->getTable())
            ->where('entidade_id', $alvo->getKey())
            ->where('acao', 'atualizado')
            ->where('criado_em', '>=', now()->subSeconds(5))
            ->orderByDesc('id')
            ->first();

        $depois = array_filter([
            'motivo' => $motivo,
            // Rótulo do alvo no momento da ação. Sem ele, um cliente renomeado
            // depois faz a trilha antiga apontar para outro nome — e o histórico
            // deve dizer o que era verdade NAQUELE momento.
            'alvo' => $this->rotuloDoAlvo($alvo),
        ] + $contexto, fn ($v) => $v !== null && $v !== '');

        if ($automatico !== null) {
            $automatico->update([
                'acao' => $acao,
                // A COLUNA tambem, e nao so o JSON. Este ramo (absorver o
                // `atualizado` recente) ficou de fora quando `motivo` virou
                // coluna no F2-06, e o efeito era silencioso: a acao com motivo
                // gravava a coluna VAZIA sempre que o alvo tinha acabado de ser
                // salvo — que e justamente o caso comum.
                'motivo' => $motivo,
                // O diff de colunas do update é preservado; só ganha o verbo
                // certo e o porquê.
                'depois' => array_merge($automatico->depois ?? [], $depois),
            ]);

            return;
        }

        AuditLog::create([
            ...app(ContextoAuditoria::class)->campos(),
            // `motivo` vira COLUNA: dentro do JSON `depois` nao dava para
            // filtrar nem exigir.
            'motivo' => $motivo,
            'empresa_id' => $alvo->getAttribute('empresa_id'),
            'entidade' => $alvo->getTable(),
            'entidade_id' => $alvo->getKey(),
            'acao' => $acao,
            'user_id' => Auth::id(),
            'antes' => null,
            'depois' => $depois,
            'ip' => $this->ipAtual(),
            'criado_em' => now(),
        ]);
    }

    /** Nome exibível do registro afetado, com os campos mais comuns primeiro. */
    private function rotuloDoAlvo(Model $alvo): ?string
    {
        foreach (['nome', 'razao_social', 'descricao', 'documento', 'numero'] as $campo) {
            $valor = $alvo->getAttribute($campo);
            if (is_string($valor) && $valor !== '') {
                return $valor;
            }
        }

        return null;
    }

    private function ipAtual(): ?string
    {
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null; // CLI/ETL/jobs — sem request.
        }
    }
}
