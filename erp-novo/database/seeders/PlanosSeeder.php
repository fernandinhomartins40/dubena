<?php

namespace Database\Seeders;

use App\Domain\Saas\RecursoCatalogo;
use App\Models\Saas\Plano;
use App\Models\Saas\PlanoLimite;
use Illuminate\Database\Seeder;

/**
 * Planos SaaS (P2 / F2-03) — idempotente, roda no deploy junto do RBAC.
 *
 * DOIS planos iniciais, ambos pagos: não há free.
 *
 * Este seeder cria apenas um PONTO DE PARTIDA. Preço, recursos e limites são
 * decisão comercial do dono e se editam no painel SuperAdmin (Planos) — nada
 * disso fica fixo no código.
 *
 * Em particular, o seeder NÃO declara tetos: sem teto declarado o plano é
 * ilimitado, e o dono define os números quando desenhar a grade. A alternativa
 * seria eu derivá-los do uso da Dubena, e uma única revenda não pode virar a
 * régua de um produto feito para N revendas — foi exatamente esse o erro que a
 * transformação SaaS existe para desfazer.
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
        //
        $this->plano(
            'completo',
            'Completo',
            'Tudo do Essencial mais monitoramento GPS, CRM, frota, marketplace, tempo real e relatórios gerenciais.',
            749.90,
            RecursoCatalogo::chaves(),
        );

        // F2-04 — plano de TRANSIÇÃO, não oferta.
        //
        // Existe para uma finalidade só: conservar o acesso de quem já opera
        // enquanto o fail-open é removido. Recebe o catálogo inteiro e nenhum
        // teto, porque estreitar quem já roda seria a transição quebrando a
        // operação — exatamente o que ela deveria evitar.
        //
        // Preço zero não é generosidade: é a marca de que este plano não foi
        // negociado. Quem estiver nele precisa migrar para um plano vendável,
        // e é o relatório de `saas:legacy:status` que cobra essa migração.
        $legado = $this->plano(
            Plano::SLUG_LEGADO,
            'Legacy Full (transição)',
            'Plano de transição das empresas que já operavam antes da licença passar a decidir. Não é vendável: migrar para Essencial ou Completo.',
            0.0,
            RecursoCatalogo::chaves(),
        );
        $legado->update(['transitorio' => true]);

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
    private function plano(string $slug, string $nome, string $descricao, float $preco, array $recursos, array $limites = []): Plano
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

        // Limites: o seeder só SEMEIA o que ainda não existe, e nunca apaga.
        //
        // A grade é editada no painel, e este seeder roda a cada deploy —
        // sincronizar (apagando o que não está na lista) desfaria silenciosamente
        // a decisão comercial do dono toda vez que subisse uma versão.
        $limitesValidos = array_filter(
            $limites,
            fn ($chave) => RecursoCatalogo::limiteExiste($chave),
            ARRAY_FILTER_USE_KEY,
        );

        foreach ($limitesValidos as $chave => $valor) {
            PlanoLimite::query()->firstOrCreate(
                ['plano_id' => $plano->id, 'limite_chave' => $chave],
                ['valor' => $valor],
            );
        }

        return $plano;
    }
}
