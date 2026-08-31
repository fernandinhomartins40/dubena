<?php

namespace App\Console\Commands;

use App\Domain\Contrato\ColetorDeSchema;
use Illuminate\Console\Command;

/**
 * F2-01 — gera o contrato de request/response por rota.
 *
 * O manifesto de API (`api:manifest`) diz quais endpoints existem. Isso pega
 * rota removida, mas não pega o que quebra a SPA e os apps com muito mais
 * frequência: um campo que some do payload, um obrigatório que aparece, um tipo
 * que muda. Nada disso altera a lista de endpoints.
 *
 * O contrato é capturado RODANDO A SUÍTE com a coleta ligada, porque só 5 rotas
 * usam `FormRequest` — as outras 217 validam inline, muitas com regras montadas
 * em tempo de execução. Um extrator estático descreveria bem uma metade e mal a
 * outra, e um contrato que erra metade do sistema é pior que nenhum: dá
 * confiança onde não deve.
 *
 * Consequência honesta: a cobertura do contrato é a cobertura da suíte. Rota não
 * exercitada não entra — e o relatório diz quantas ficaram de fora, que é uma
 * informação útil por si só.
 *
 * ## Duas etapas, de propósito
 *
 * Este comando NÃO dispara a suíte. A primeira versão fazia isso com um
 * `Process` aninhado e ele falhava mudo no Windows — sem saída, sem erro, sem
 * arquivo, o que é o pior modo de uma ferramenta falhar. Rodar a suíte é
 * trabalho do shell, que já sabe fazê-lo e mostra o progresso:
 *
 *   API_SCHEMA_CAPTURA=1 php artisan test --no-coverage
 *   php artisan api:schema
 */
class ApiSchemaGerar extends Command
{
    public const CAMINHO = 'database/api-schema.json';

    protected $signature = 'api:schema
        {--check : falha se o contrato mudou, em vez de gravar}';

    protected $description = 'Gera (ou confere) o contrato de request/response por rota, capturado da suíte (F2-01).';

    public function handle(): int
    {
        $arquivo = base_path(self::CAMINHO);
        $capturado = base_path(ColetorDeSchema::CAMINHO_CAPTURA);

        if (! is_file($capturado)) {
            $this->error('Nada capturado ainda. Rode antes:');
            $this->line('  API_SCHEMA_CAPTURA=1 php artisan test --no-coverage');

            return self::FAILURE;
        }

        $novo = json_decode((string) file_get_contents($capturado), true) ?: [];
        $this->line('Rotas com contrato capturado: '.count($novo));
        $this->relatarCobertura(array_keys($novo));

        if ($this->option('check')) {
            $salvo = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : [];

            if ($this->diferencas($salvo, $novo) === []) {
                $this->info('Contrato de schema íntegro.');

                return self::SUCCESS;
            }

            $this->error('Drift de SCHEMA detectado:');
            foreach ($this->diferencas($salvo, $novo) as $linha) {
                $this->line('  '.$linha);
            }

            return self::FAILURE;
        }

        file_put_contents($arquivo, json_encode($novo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->info('Contrato gravado: '.self::CAMINHO);

        return self::SUCCESS;
    }

    /**
     * Diferenças entre o contrato salvo e o capturado.
     *
     * Campo que SOME de uma resposta é o caso mais grave — é o que quebra o
     * consumidor silenciosamente — então ele aparece primeiro.
     *
     * @param  array<string, mixed>  $salvo
     * @param  array<string, mixed>  $novo
     * @return list<string>
     */
    private function diferencas(array $salvo, array $novo): array
    {
        $linhas = [];

        foreach ($salvo as $rota => $antes) {
            if (! isset($novo[$rota])) {
                // Rota que sumiu do contrato pode ser rota removida (grave) ou
                // teste removido (não é contrato). O manifesto de existência
                // distingue os dois; aqui só se anota.
                $linhas[] = "~ {$rota} não foi exercitado nesta execução";

                continue;
            }

            foreach (array_diff_key($antes['response'] ?? [], $novo[$rota]['response'] ?? []) as $campo => $tipo) {
                $linhas[] = "- {$rota}: campo `{$campo}` SUMIU da resposta ({$tipo})";
            }

            foreach ($novo[$rota]['request'] ?? [] as $campo => $obrigatorio) {
                $antesObrig = $antes['request'][$campo] ?? null;
                if ($obrigatorio && $antesObrig === false) {
                    $linhas[] = "! {$rota}: `{$campo}` passou a ser OBRIGATÓRIO";
                }
            }
        }

        return $linhas;
    }

    /** @param  list<string>  $comContrato */
    private function relatarCobertura(array $comContrato): void
    {
        $todas = ApiManifestGerar::coletar();
        $faltando = array_values(array_diff($todas, $comContrato));

        $pct = $todas === [] ? 0 : (int) round((count($todas) - count($faltando)) / count($todas) * 100);
        $this->line("Cobertura do contrato: {$pct}% (".count($faltando).' rotas sem teste que as exercite)');
    }
}
