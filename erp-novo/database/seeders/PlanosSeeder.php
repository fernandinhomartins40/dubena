<?php

namespace Database\Seeders;

use App\Domain\Saas\RecursoCatalogo;
use App\Models\Saas\Plano;
use Illuminate\Database\Seeder;

/**
 * Planos SaaS iniciais (P2) — idempotente.
 *
 * Cria 3 planos vendáveis com recursos crescentes (chaves do RecursoCatalogo).
 * Roda no deploy junto do RBAC; novos recursos declarados no catálogo entram nos
 * planos aqui. NÃO cria assinaturas — empresas sem assinatura têm tudo liberado
 * (fail-open do LicencaService), preservando instalações pré-SaaS.
 */
class PlanosSeeder extends Seeder
{
    public function run(): void
    {
        $todos = RecursoCatalogo::chaves();

        // Básico: operação essencial (sem marketplace/tempo real/entregador).
        $this->plano('basico', 'Básico', 'Operação essencial da revenda.', 149.90, [
            'app_consumidor', 'cobranca', 'nfce',
        ]);

        // Pro: + marketplace, CRM, frota, monitora, relatórios.
        $this->plano('pro', 'Pro', 'Revenda completa com descoberta no app.', 299.90, [
            'app_consumidor', 'cobranca', 'nfce', 'marketplace', 'crm', 'frota',
            'monitora', 'relatorios_avancados',
        ]);

        // Enterprise: todos os recursos (inclui app entregador + tempo real).
        $this->plano('enterprise', 'Enterprise', 'Plataforma completa, tempo real e entregador.', 599.90, $todos);
    }

    /**
     * @param  list<string>  $recursos
     */
    private function plano(string $slug, string $nome, string $descricao, float $preco, array $recursos): void
    {
        $plano = Plano::query()->updateOrCreate(
            ['slug' => $slug],
            ['nome' => $nome, 'descricao' => $descricao, 'preco_mensal' => $preco, 'ativo' => true],
        );

        // Sincroniza os recursos do plano (só chaves válidas do catálogo).
        $validos = array_values(array_filter($recursos, fn ($c) => RecursoCatalogo::existe($c)));

        $plano->recursos()->whereNotIn('recurso_chave', $validos)->delete();
        foreach ($validos as $chave) {
            $plano->recursos()->updateOrCreate(['recurso_chave' => $chave], []);
        }
    }
}
