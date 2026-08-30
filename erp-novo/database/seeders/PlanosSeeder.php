<?php

namespace Database\Seeders;

use App\Domain\Saas\RecursoCatalogo;
use App\Models\Saas\Plano;
use Illuminate\Database\Seeder;

/**
 * Planos SaaS (P2 / F2-03) — idempotente, roda no deploy junto do RBAC.
 *
 * DOIS planos, ambos pagos: não há free. A régua entre eles é o que a revenda
 * usa de verdade, medido na base real da Dubena antes de desenhar a grade:
 *
 *   241.021 notas fiscais   → fiscal é essencial, não diferencial
 *    21.135 boletos + 4.961 PIX → cobrança idem
 *    16 milhões de posições GPS  → monitoramento é uso pesado, de quem tem frota
 *     3.097 pós-vendas / 20 sorteios → CRM é maturidade, não partida
 *
 * Ou seja: o que toda revenda precisa para operar no dia um fica no ESSENCIAL;
 * o que só faz sentido com escala (frota rastreada, CRM, marketplace, tempo
 * real, relatórios gerenciais) fica no COMPLETO.
 *
 * O seeder NÃO cria assinaturas — quem vincula empresa a plano é
 * `saas:assinatura:criar`, com data e origem explícitas. Empresa sem assinatura
 * fica sem recurso nenhum: `LicencaService` é fail-closed (o comentário antigo
 * aqui dizia "fail-open", e estava desatualizado — verificado em teste).
 */
class PlanosSeeder extends Seeder
{
    /**
     * Recursos que toda revenda precisa para operar desde o primeiro dia.
     *
     * @var list<string>
     */
    public const ESSENCIAL = [
        'app_consumidor',
        'app_entregador',
        'cobranca',
        'nfce',
    ];

    public function run(): void
    {
        $this->plano(
            'essencial',
            'Essencial',
            'Operação completa da revenda: pedidos pelo app, entrega, cobrança registrada e emissão fiscal.',
            349.90,
            self::ESSENCIAL,
        );

        // Completo = tudo do catálogo. Declarado por diferença, não por lista
        // fixa: recurso novo no catálogo entra aqui sozinho, e o que fica de
        // fora do Essencial vira diferencial por construção.
        $this->plano(
            'completo',
            'Completo',
            'Tudo do Essencial mais monitoramento GPS, CRM, frota, marketplace, tempo real e relatórios gerenciais.',
            749.90,
            RecursoCatalogo::chaves(),
        );

        // Planos legados da fase P2. Desativados, não excluídos: uma assinatura
        // antiga pode apontar para eles, e apagar a linha deixaria a assinatura
        // órfã — sem plano, o tenant perderia todos os recursos de uma vez.
        Plano::query()
            ->whereIn('slug', ['basico', 'pro', 'enterprise'])
            ->update(['ativo' => false]);
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
