<?php

namespace Database\Seeders;

use App\Domain\Saas\RecursoCatalogo;
use App\Models\Saas\Plano;
use App\Models\Saas\PlanoLimite;
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

    /**
     * Tetos do Essencial. `null` = ilimitado.
     *
     * A régua veio da própria Dubena: 11 unidades e 82 usuários é uma REDE, não
     * uma revenda de bairro. O Essencial atende quem opera uma ou duas lojas;
     * quem passa disso está no Completo por definição de negócio, não por
     * limitação técnica.
     *
     * @var array<string, int|null>
     */
    private const LIMITES_ESSENCIAL = [
        'empresas' => 2,
        'usuarios' => 15,
        'veiculos_monitorados' => 0, // monitoramento não faz parte do plano
    ];

    public function run(): void
    {
        $this->plano(
            'essencial',
            'Essencial',
            'Operação completa da revenda: pedidos pelo app, entrega, cobrança registrada e emissão fiscal.',
            349.90,
            self::ESSENCIAL,
            self::LIMITES_ESSENCIAL,
        );

        // Completo = tudo do catálogo. Declarado por diferença, não por lista
        // fixa: recurso novo no catálogo entra aqui sozinho, e o que fica de
        // fora do Essencial vira diferencial por construção.
        //
        // Sem teto, e declarado explicitamente como `null` em vez de omitido:
        // omitir também libera, mas por acidente — a diferença importa para quem
        // for revisar a grade depois.
        $this->plano(
            'completo',
            'Completo',
            'Tudo do Essencial mais monitoramento GPS, CRM, frota, marketplace, tempo real e relatórios gerenciais.',
            749.90,
            RecursoCatalogo::chaves(),
            array_fill_keys(RecursoCatalogo::chavesDeLimite(), null),
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
     * @param  array<string, int|null>  $limites
     */
    private function plano(string $slug, string $nome, string $descricao, float $preco, array $recursos, array $limites = []): void
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

        // Limites: mesma disciplina — só chaves do catálogo, e o que sai da
        // lista é removido, para o plano não carregar teto de uma grade antiga.
        $limitesValidos = array_filter(
            $limites,
            fn ($chave) => RecursoCatalogo::limiteExiste($chave),
            ARRAY_FILTER_USE_KEY,
        );

        PlanoLimite::query()
            ->where('plano_id', $plano->id)
            ->whereNotIn('limite_chave', array_keys($limitesValidos))
            ->delete();

        foreach ($limitesValidos as $chave => $valor) {
            PlanoLimite::query()->updateOrCreate(
                ['plano_id' => $plano->id, 'limite_chave' => $chave],
                ['valor' => $valor],
            );
        }
    }
}
