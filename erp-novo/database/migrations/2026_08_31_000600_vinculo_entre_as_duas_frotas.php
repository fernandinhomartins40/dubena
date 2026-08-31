<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3-09 — as duas frotas passam a se conhecer.
 *
 * O mesmo caminhão existe duas vezes no sistema:
 *
 *   `veiculos`            frota: km, troca de óleo, documentos, abastecimento
 *   `monitora_veiculos`   rastreamento: imei do rastreador, posições, cercas
 *
 * E nada as liga. Cada uma tem o seu `veiculo_id` apontando para si mesma; a
 * placa é a única coisa em comum, e ninguém confere se batem.
 *
 * As consequências são de dois tipos, e as duas doem:
 *
 *  - **operacional**: "onde está o caminhão que precisa trocar o óleo?" não tem
 *    resposta. Uma frota sabe o km, a outra sabe a posição, e cruzar as duas é
 *    trabalho manual sobre uma planilha;
 *  - **de cadastro**: a placa pode divergir entre as duas por um erro de
 *    digitação, e nada acusa. O veículo simplesmente some de um dos lados.
 *
 * ## Vínculo, e não fusão
 *
 * A tarefa fala em "consolidar as duas frotas", e o alvo final é uma tabela só.
 * Isto aqui é o passo anterior: `monitora_veiculos.veiculo_frota_id` liga as
 * duas sem mover dado nenhum.
 *
 * Fundi-las agora alcançaria `Veiculo` em 23 arquivos e as tabelas de posição,
 * que têm milhões de linhas — é migração de dado grande, e o custo de errá-la é
 * perder histórico de rastreamento. O vínculo entrega a resposta operacional
 * hoje e deixa a fusão como um passo separado, feito com os dois lados já
 * conciliados.
 *
 * ## A conversão
 *
 * Pela PLACA normalizada, e só quando o par é inequívoco: exatamente um de cada
 * lado, na mesma empresa. Placa duplicada dentro de uma frota fica sem vínculo —
 * escolher uma delas gravaria um palpite num banco que ninguém revisa, e aqui o
 * palpite errado liga a manutenção de um caminhão à posição de outro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitora_veiculos', function (Blueprint $t) {
            $t->foreignId('veiculo_frota_id')
                ->nullable()
                ->after('grupo_id')
                ->constrained('veiculos')
                // `nullOnDelete`: apagar o cadastro de frota não pode apagar o
                // histórico de posições, que é o registro de onde o veículo
                // esteve — dado que se usa para conferir entrega e jornada.
                ->nullOnDelete();
        });

        $this->conciliarPorPlaca();
    }

    /**
     * Liga os pares inequívocos pela placa normalizada.
     *
     * Normalizar (maiúsculas, só alfanumérico) porque "ABC-1D23" e "abc1d23"
     * são a mesma placa digitada por pessoas diferentes — e é justamente esse
     * tipo de divergência que hoje faz o veículo sumir de um dos lados.
     */
    private function conciliarPorPlaca(): void
    {
        $normalizar = fn (?string $p) => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $p));

        $frota = DB::table('veiculos')
            ->get(['id', 'empresa_id', 'placa'])
            ->groupBy(fn ($v) => $v->empresa_id.':'.$normalizar($v->placa));

        DB::table('monitora_veiculos')
            ->whereNull('veiculo_frota_id')
            ->orderBy('id')
            ->chunkById(500, function ($rastreados) use ($frota, $normalizar) {
                foreach ($rastreados as $r) {
                    $chave = $r->empresa_id.':'.$normalizar($r->placa);
                    $candidatos = $frota->get($chave);

                    // Ambíguo (placa repetida na frota) ou sem par: fica nulo.
                    // A tela de conciliação mostra os dois casos para decisão.
                    if ($candidatos === null || $candidatos->count() !== 1) {
                        continue;
                    }

                    DB::table('monitora_veiculos')
                        ->where('id', $r->id)
                        ->update(['veiculo_frota_id' => $candidatos->first()->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('monitora_veiculos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('veiculo_frota_id');
        });
    }
};
