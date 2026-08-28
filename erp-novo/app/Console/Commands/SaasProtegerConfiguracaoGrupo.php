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
        $previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_owner');
        try {
            $result = $this->option('apply') ? $protector->protect() : $protector->preview();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::setDefaultConnection($previousConnection);
        }

        $this->info(($this->option('apply') ? 'Protecao aplicada' : 'Preview sem alteracoes').': '.json_encode($result));

        return self::SUCCESS;
    }
}
