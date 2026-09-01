<?php

namespace App\Domain\Integracao;

use Illuminate\Support\Facades\DB;

/**
 * F6-01 — quem chamou, quantas vezes, e quanto isso custa.
 *
 * Três APIs do Google são cobradas por chamada (geocoding, routes, roads) e o
 * sistema não sabia quantas fazia nem por conta de quem. Num SaaS isso significa
 * fatura sem dono, quota que estoura sem aviso e fallback silencioso para a
 * chave da plataforma.
 *
 * ## Nunca derruba a chamada
 *
 * Toda escrita é protegida: se o registro falhar, a **integração continua**.
 * Instrumentação que interrompe o que ela observa inverte a prioridade — a
 * mesma decisão do `RegistroDaConversao`.
 *
 * ## Preço estimado, e é uma estimativa mesmo
 *
 * O preço real vem da fatura e muda por contrato e por volume. O que está aqui é
 * **ordem de grandeza**, para a revenda decidir se investiga — cravar o preço
 * exato no código seria número de negócio virando constante, que é o que este
 * plano inteiro combate.
 *
 * Quem quiser o valor certo cadastra na configuração; o default serve para o
 * número não ser zero, que não ajuda ninguém.
 */
class ConsumoDeIntegracao
{
    /**
     * Preço aproximado por 1000 chamadas, em centavos de real.
     *
     * Ordem de grandeza pública do Google Maps Platform em 2026 (US$ 5/1000 para
     * geocoding, US$ 5/1000 para roads, US$ 10/1000 para routes com trânsito),
     * convertidos por volta de R$ 5,50/US$. **Não é preço de fatura** — serve
     * para o número sair diferente de zero e dar noção de escala.
     *
     * @var array<string,int>
     */
    private const CENTAVOS_POR_MIL = [
        'geocoding' => 2750,
        'roads' => 2750,
        'routes' => 5500,
    ];

    /**
     * Registra uma chamada.
     *
     * `$empresaId` e `$grupoId` nulos significam **chave da plataforma** — o
     * caso que hoje só aparece num `Log::warning` e que ninguém soma.
     */
    public function registrar(
        string $servico,
        ?int $empresaId = null,
        ?int $grupoId = null,
        ?string $finalidade = null,
        bool $erro = false,
        ?string $mensagemErro = null,
    ): void {
        try {
            $chave = [
                'empresa_id' => $empresaId,
                'grupo_id' => $grupoId,
                'servico' => $servico,
                'finalidade' => $finalidade,
                'dia' => now()->toDateString(),
            ];

            // O tenant vem da EMPRESA. Coluna criada e deixada nula e o defeito
            // que F1 e F4 encontraram duas vezes nesta base: parece resolvida e
            // nao responde pergunta nenhuma. Nulo aqui so quando a chave e da
            // plataforma — que e o mesmo caso de `empresa_id` nulo.
            $tenantAccountId = $empresaId !== null
                ? DB::table('empresas')->where('id', $empresaId)->value('tenant_account_id')
                : null;

            $existente = DB::table('integracao_consumos')->where($chave)->first();

            $custoDaChamada = (int) round(
                (self::CENTAVOS_POR_MIL[$servico] ?? 0) / 1000,
            );

            if ($existente === null) {
                DB::table('integracao_consumos')->insert($chave + [
                    'tenant_account_id' => $tenantAccountId,
                    'chamadas' => 1,
                    'erros' => $erro ? 1 : 0,
                    'ultimo_erro_em' => $erro ? now() : null,
                    'ultimo_erro' => $erro ? mb_substr((string) $mensagemErro, 0, 255) : null,
                    'custo_centavos' => $custoDaChamada,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return;
            }

            // `increment` e não leitura-soma-escrita: dois workers
            // geocodificando em paralelo é o caso normal, e a versão em PHP
            // perderia contagem sem ninguém notar.
            DB::table('integracao_consumos')->where('id', $existente->id)->update([
                'chamadas' => DB::raw('chamadas + 1'),
                'erros' => DB::raw('erros + '.($erro ? 1 : 0)),
                'custo_centavos' => DB::raw('custo_centavos + '.$custoDaChamada),
                'ultimo_erro_em' => $erro ? now() : $existente->ultimo_erro_em,
                'ultimo_erro' => $erro ? mb_substr((string) $mensagemErro, 0, 255) : $existente->ultimo_erro,
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Sem tabela (banco antigo) ou falha de escrita: a integração segue.
        }
    }

    /**
     * Consumo de um dono no período, por serviço.
     *
     * @return array<string, array{chamadas:int, erros:int, custo_centavos:int}>
     */
    public function resumo(?int $empresaId, string $inicio, string $fim): array
    {
        try {
            $linhas = DB::table('integracao_consumos')
                ->when($empresaId !== null,
                    fn ($q) => $q->where('empresa_id', $empresaId),
                    fn ($q) => $q->whereNull('empresa_id'))
                ->whereDate('dia', '>=', $inicio)
                ->whereDate('dia', '<=', $fim)
                ->selectRaw('servico, sum(chamadas) as chamadas, sum(erros) as erros, sum(custo_centavos) as custo')
                ->groupBy('servico')
                ->get();
        } catch (\Throwable) {
            return [];
        }

        $saida = [];

        foreach ($linhas as $l) {
            $saida[$l->servico] = [
                'chamadas' => (int) $l->chamadas,
                'erros' => (int) $l->erros,
                'custo_centavos' => (int) $l->custo,
            ];
        }

        return $saida;
    }
}
