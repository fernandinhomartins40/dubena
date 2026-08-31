<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Frota\Veiculo;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F2-08 — troca adversarial de IDs, varrendo as rotas de verdade.
 *
 * O ataque é o mais barato que existe num SaaS: o usuário legítimo da revenda A
 * troca o id na URL pelo de um registro da revenda B. Não precisa de ferramenta,
 * nem de credencial roubada — basta editar o número na barra de endereço.
 *
 * Testes cross-tenant escritos à mão cobrem o que alguém lembrou de escrever.
 * Este aqui **descobre as rotas sozinho**, a partir do roteador, e por isso pega
 * também o que ninguém pensou em cobrir — inclusive rotas que ainda não existem
 * quando este arquivo é escrito.
 *
 * A defesa esperada é 404, não 403: dizer "existe, mas você não pode" já entrega
 * a informação de que aquele id existe em algum lugar. O global scope de
 * `BelongsToTenant` produz 404 naturalmente, porque o registro simplesmente não
 * está no conjunto visível. 403 também é aceito — nega o acesso —, mas 200 é
 * vazamento e 500 é a aplicação quebrando em vez de negar.
 */
class TrocaAdversarialDeIdsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Recurso da URI => model cujo registro será criado no tenant VÍTIMA.
     *
     * Só entram recursos cujo dado pertence a uma empresa. Catálogos de
     * plataforma (cidades, logradouros) são globais de propósito: o id de uma
     * cidade é o mesmo para todo mundo, e testá-los aqui afirmaria um isolamento
     * que não existe nem deve existir.
     *
     * @var array<string, class-string<Model>>
     */
    private const RECURSOS = [
        'clientes' => Cliente::class,
        'produtos' => Produto::class,
        'colaboradores' => Colaborador::class,
        'veiculos' => Veiculo::class,
        'financeiro' => Financeiro::class,
    ];

    /** Métodos que só leem — os únicos seguros de disparar em massa. */
    private const METODOS_SEGUROS = ['GET'];

    /** @return array{User, Empresa} atacante com papel completo no PRÓPRIO tenant */
    private function atacante(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        FronteiraTenant::papelAdministrador($user, $empresa->id);

        return [$user->fresh(), $empresa];
    }

    /** Empresa de OUTRO grupo — outro tenant, não outra filial. */
    private function vitima(): Empresa
    {
        return Empresa::factory()->create();
    }

    /**
     * Rotas GET de `api/admin/{recurso}/{id}` para os recursos mapeados.
     *
     * @return list<array{string, string}> [recurso, uri]
     */
    private function rotasDeLeituraComId(): array
    {
        $achadas = [];

        foreach (Route::getRoutes() as $rota) {
            $uri = $rota->uri();

            if (! str_starts_with($uri, 'api/admin/') || ! str_contains($uri, '{id}')) {
                continue;
            }

            if (array_intersect($rota->methods(), self::METODOS_SEGUROS) === []) {
                continue;
            }

            $recurso = explode('/', substr($uri, strlen('api/admin/')))[0];

            if (isset(self::RECURSOS[$recurso])) {
                $achadas[] = [$recurso, $uri];
            }
        }

        return $achadas;
    }

    /**
     * O teste central: nenhuma rota de leitura devolve 200 para id alheio.
     */
    public function test_nenhuma_rota_de_leitura_devolve_registro_de_outro_tenant(): void
    {
        [$user] = $this->atacante();
        $vitima = $this->vitima();

        $alvos = [];
        foreach (self::RECURSOS as $recurso => $model) {
            $alvos[$recurso] = $this->criarNoTenant($model, $vitima);
        }

        $rotas = $this->rotasDeLeituraComId();
        $this->assertNotEmpty($rotas, 'a varredura não encontrou rota nenhuma — o mapa quebrou');

        $vazamentos = [];
        $quebras = [];

        foreach ($rotas as [$recurso, $uri]) {
            $alvo = $alvos[$recurso] ?? null;
            if ($alvo === null) {
                continue;
            }

            $url = '/'.str_replace(['{id}', '{tipo}', '{entidade}'], [(string) $alvo->getKey(), '1', 'clientes'], $uri);

            // Rota que ainda tenha outro parâmetro não substituído não é
            // exercitável aqui; ignorar é melhor que montar uma URL inventada.
            if (str_contains($url, '{')) {
                continue;
            }

            $resposta = $this->actingAs($user, 'sanctum')->getJson($url);
            $status = $resposta->status();

            if ($status === 200) {
                $vazamentos[] = "{$uri} devolveu 200 para id do tenant vizinho";
            } elseif ($status >= 500) {
                $quebras[] = "{$uri} devolveu {$status} — quebrou em vez de negar";
            }
        }

        $this->assertSame([], $vazamentos, "Vazamento entre tenants:\n".implode("\n", $vazamentos));
        $this->assertSame([], $quebras, "Rotas quebrando em id alheio:\n".implode("\n", $quebras));
    }

    /**
     * O contraponto indispensável: as mesmas rotas RESPONDEM para o id próprio.
     *
     * Sem isto, o teste acima passaria com a aplicação inteira fora do ar — 404
     * em tudo também é "nenhum 200".
     */
    public function test_as_mesmas_rotas_respondem_para_o_id_proprio(): void
    {
        [$user, $empresa] = $this->atacante();

        $proprios = [];
        foreach (self::RECURSOS as $recurso => $model) {
            $proprios[$recurso] = $this->criarNoTenant($model, $empresa);
        }

        $atendidas = 0;
        foreach ($this->rotasDeLeituraComId() as [$recurso, $uri]) {
            $alvo = $proprios[$recurso] ?? null;
            if ($alvo === null) {
                continue;
            }

            $url = '/'.str_replace(['{id}', '{tipo}', '{entidade}'], [(string) $alvo->getKey(), '1', 'clientes'], $uri);
            if (str_contains($url, '{')) {
                continue;
            }

            if ($this->actingAs($user, 'sanctum')->getJson($url)->status() === 200) {
                $atendidas++;
            }
        }

        $this->assertGreaterThan(
            0,
            $atendidas,
            'nenhuma rota respondeu para o id próprio — o teste adversarial estaria medindo o vazio',
        );
    }

    /**
     * Guarda contra o mapa envelhecer: recurso novo com `{id}` precisa entrar
     * em `RECURSOS` ou ser reconhecido como catálogo de plataforma.
     *
     * Sem isto, este arquivo daria uma sensação de cobertura que vai encolhendo
     * silenciosamente a cada rota nova.
     */
    public function test_todo_recurso_com_id_esta_mapeado_ou_declarado_global(): void
    {
        // Recursos ainda FORA da varredura automática.
        //
        // Dois motivos distintos, e é importante não confundi-los:
        //
        //  - catálogos de plataforma (`cidades`, `logradouros`, `grupos`), cujo
        //    id é o mesmo para todo mundo de propósito — testá-los aqui
        //    afirmaria um isolamento que não existe nem deve existir;
        //
        //  - dados de empresa cujo registro depende de uma cadeia de
        //    obrigatórios (um comodato exige cliente, uma nota exige pedido e
        //    itens). Esses TÊM isolamento e são cobertos pelos testes
        //    cross-tenant específicos de cada módulo; entram aqui conforme
        //    ganharem factory.
        //
        // A lista é uma declaração explícita, não uma isenção: recurso novo com
        // `{id}` falha este teste até alguém decidir a que grupo ele pertence.
        $foraDaVarredura = [
            'cidades', 'logradouros', 'geo', 'grupos', 'empresas', 'papeis',
            'auditoria', 'seguranca', 'cadastros', 'unidades', 'regioes',
            'central', 'central-vendas', 'estoque', 'fiscal',
            'notas', 'boletos', 'cheques', 'monitora', 'missoes', 'checklists',
            'documentos', 'bens', 'alertas', 'alcadas', 'usuarios', 'setores',
            'setores-org', 'sorteios', 'taxas-entrega', 'telefonia', 'vale-gas',
            'cobranca', 'cupons-fiscais', 'franqueados', 'gasdopovo', 'mcmm',
            'pos-vendas', 'produto-config', 'promocoes', 'convenios', 'metas',
            'departamentos', 'pedidos', 'comodatos',
        ];

        $descobertos = [];
        foreach (Route::getRoutes() as $rota) {
            $uri = $rota->uri();
            if (str_starts_with($uri, 'api/admin/') && str_contains($uri, '{id}')) {
                $descobertos[] = explode('/', substr($uri, strlen('api/admin/')))[0];
            }
        }

        $naoCobertos = array_values(array_unique(array_diff(
            $descobertos,
            array_keys(self::RECURSOS),
            $foraDaVarredura,
        )));

        $this->assertSame(
            [],
            $naoCobertos,
            'Recurso com {id} fora do mapa adversarial: '.implode(', ', $naoCobertos)
            .'. Acrescente-o em RECURSOS (se tiver factory) ou declare em $foraDaVarredura, dizendo por quê.',
        );
    }

    /**
     * Cria um registro do model dentro da empresa indicada.
     *
     * Usa factory quando existe; senão insere o mínimo direto pelo model. Um
     * registro cru basta: o que se mede aqui é a rota alcançar (ou não) um id de
     * outro tenant, não a integridade do registro.
     */
    private function criarNoTenant(string $model, Empresa $empresa): Model
    {
        $chaves = ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id];

        if (method_exists($model, 'factory')) {
            try {
                return $model::factory()->create($chaves);
            } catch (\Throwable) {
                // Sem factory utilizável: cai para a inserção crua abaixo.
            }
        }

        $registro = new $model;
        $registro->forceFill($chaves);
        $registro->save();

        return $registro;
    }
}
