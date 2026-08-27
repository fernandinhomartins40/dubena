<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comodato tem DOIS sentidos, e o modelo só conhecia um.
 *
 * Confirmado com o dono em 2026-08-24: *"Temos ambas opções: de nós pra cliente,
 * e de terceiros pra nós."* A SUPERGASBRAS empresta 5.583 cascos para a revenda
 * — é comodato legítimo, na mão contrária do que o sistema pressupunha.
 *
 * Sem essa distinção, três coisas medidas em produção saem erradas:
 *
 * 1. A vigilância por giro trata a distribuidora como cliente vigiado. Giro zero
 *    num fornecedor que nunca vai "comprar de volta" — o alerta mais grave da
 *    base inteira é falso.
 * 2. As estatísticas dizem "em poder de clientes" incluindo o que está em poder
 *    da própria revenda. 76% do parque contado do lado errado.
 * 3. O contrato consolidado listaria os recebidos junto com os concedidos, num
 *    texto que descreve obrigação de devolução DO cliente.
 *
 * `CONCEDIDO` é o default porque é o que os 229 comodatos existentes são — a
 * exceção é a distribuidora, marcada logo abaixo pelo CNPJ dela. Inverter o
 * default reclassificaria a carteira inteira de uma vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comodatos', function (Blueprint $t) {
            // CONCEDIDO (nós → cliente) | RECEBIDO (terceiro → nós)
            $t->string('sentido', 12)->default('CONCEDIDO');
        });

        // A SUPERGASBRAS é a distribuidora: o que está registrado no nome dela
        // é casco que a revenda RECEBEU. Marcar por CNPJ e não por id porque o
        // id do cliente varia entre bancos (homologação, produção, teste).
        DB::table('comodatos')
            ->whereIn('cliente_id', function ($q) {
                $q->select('id')->from('clientes')->where('cnpj', 'like', '19791896%');
            })
            ->update(['sentido' => 'RECEBIDO']);

        // Consulta quente: separar as duas contas é o que toda estatística de
        // comodato passa a fazer, e a vigilância filtra por aqui a cada rodada.
        Schema::table('comodatos', function (Blueprint $t) {
            $t->index(['empresa_id', 'sentido', 'situacao'], 'comodatos_empresa_sentido_situacao_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comodatos', function (Blueprint $t) {
            $t->dropIndex('comodatos_empresa_sentido_situacao_idx');
            $t->dropColumn('sentido');
        });
    }
};
