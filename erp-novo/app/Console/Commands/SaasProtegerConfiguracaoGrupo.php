<?php

namespace App\Console\Commands;

use App\Domain\Tenant\TenantLegacyGroupConfigurationProtector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

class SaasProtegerConfiguracaoGrupo extends Command
{
    protected $signature = 'saas:tenant:proteger-configuracao-grupo {--apply : Executa a protecao apos revisar o mapeamento documental}';

    protected $description = 'Ativa RLS para configuracoes group-scoped somente quando todas as linhas possuem ponte documental aprovada.';

    public function handle(TenantLegacyGroupConfigurationProtector $protector): int
    {
        if (! $this->option('apply')) {
            $this->error('Comando recusado: revise o JSON documental e informe --apply para alterar policies.');

            return self::FAILURE;
        }

        $previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_owner');
        try {
            $result = $protector->protect();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::setDefaultConnection($previousConnection);
        }

        $this->info('Protecao aplicada: '.json_encode($result));

        return self::SUCCESS;
    }
}
