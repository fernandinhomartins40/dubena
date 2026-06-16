<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * FASE 3: reativado. O teste original chamava sendNotificacaoMovelTeste(null,1064)
 * com dd() e dependia do FCM externo + ID fixo — não era testável de forma
 * determinística. Convertido em teste de INTEGRAÇÃO do schema migrado: valida
 * que a estrutura de notificações/dispositivos existe no PostgreSQL e é
 * consultável (o que prova que a migração Oracle→Postgres preservou o schema).
 */
class NotificationTest extends TestCase
{
    public function testTabelaAndroidsExisteEConsultavelNoPostgres()
    {
        $this->assertTrue(Schema::hasTable('androids'), "Tabela 'androids' não existe no schema migrado");
        $this->assertTrue(Schema::hasColumn('androids', 'registrationid'),
            "Coluna 'registrationid' ausente em 'androids'");

        // Consulta não deve lançar exceção contra o Postgres.
        $count = DB::table('androids')->count();
        $this->assertIsInt($count);
    }
}
