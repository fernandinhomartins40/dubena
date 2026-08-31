<?php

namespace Tests\Feature;

use App\Domain\Cobranca\Events\PixConfirmado;
use App\Domain\Logistica\Events\PedidoAtribuido;
use App\Domain\Logistica\Events\PedidoEntrouNaFila;
use App\Domain\Mobile\Events\EntregadorPosicaoAtualizada;
use App\Domain\Pedido\Events\PedidoStatusAtualizado;
use App\Models\Cobranca\PixCobranca;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6-07 — o tempo real não atravessa a fronteira do tenant.
 *
 * > *"nomes de canais carregam tenant/empresa; autorização do canal e teste de
 * > publicação/recepção cruzada; Reverb configurado explicitamente."*
 *
 * ## Sobre "nomes de canais carregam tenant"
 *
 * Os canais são `empresa.{id}.pedidos`, `empresa.{id}.central` e
 * `pedido.{id}` — sem o tenant no nome. Medi antes de mudar, e **está certo
 * assim**: `tenant_companies.empresa_id` é `unique`, ou seja, uma empresa
 * pertence a exatamente um tenant. O `empresa_id` já identifica o tenant de
 * forma inequívoca.
 *
 * Acrescentar o tenant ao nome seria redundância que **parece** segurança — e
 * pior, criaria um segundo lugar onde a fronteira é decidida, que sairia de
 * sincronia com o primeiro na primeira mudança. A premissa que sustenta isso
 * está protegida por `TenantBoundarySchemaTest`.
 *
 * ## O que este arquivo prova
 *
 * A autorização de canal já tem teste (`TempoRealTest`). O que faltava é o outro
 * lado: **o evento publicado por uma empresa nunca nomeia o canal da outra**.
 *
 * São coisas diferentes. A autorização impede entrar no canal alheio; esta
 * prova impede o evento sair no canal errado — um `empresaId` trocado na
 * construção do evento vazaria o dado para quem está legitimamente escutando o
 * próprio canal, e nenhuma autorização pegaria isso.
 */
class TempoRealCruzadoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pedido em memória, sem tocar o banco: o que os eventos leem é `id` e
     * `empresa_id`, e montar o cenário completo só adiciona ruído ao que se
     * quer provar aqui — o NOME do canal.
     */
    private function pedidoDe(Empresa $empresa): Pedido
    {
        $pedido = new Pedido;
        $pedido->forceFill(['id' => 10, 'empresa_id' => $empresa->id, 'pedidosituacao_id' => 1]);

        return $pedido;
    }

    /**
     * @param  list<Channel>  $canais
     * @return list<string>
     */
    private function nomes(array $canais): array
    {
        return array_map(fn ($c) => (string) $c->name, $canais);
    }

    public function test_evento_de_pedido_so_nomeia_canais_da_propria_empresa(): void
    {
        $minha = Empresa::factory()->create();
        $outra = Empresa::factory()->create();

        $evento = new PedidoStatusAtualizado($this->pedidoDe($minha));

        $nomes = $this->nomes($evento->broadcastOn());

        $this->assertContains("private-empresa.{$minha->id}.pedidos", $nomes);
        $this->assertNotContains("private-empresa.{$outra->id}.pedidos", $nomes);

        foreach ($nomes as $nome) {
            $this->assertStringNotContainsString(
                "empresa.{$outra->id}.",
                $nome,
                'nenhum canal do evento pode pertencer a outra empresa',
            );
        }
    }

    public function test_pix_confirmado_so_nomeia_canais_da_propria_empresa(): void
    {
        $minha = Empresa::factory()->create();
        $outra = Empresa::factory()->create();

        $cobranca = new PixCobranca;
        $cobranca->forceFill([
            'id' => 7, 'empresa_id' => $minha->id, 'pedido_id' => 7, 'txid' => 'abc',
        ]);

        $evento = new PixConfirmado($cobranca);

        foreach ($this->nomes($evento->broadcastOn()) as $nome) {
            $this->assertStringNotContainsString("empresa.{$outra->id}.", $nome);
        }
    }

    public function test_eventos_de_logistica_so_nomeiam_a_propria_central(): void
    {
        $minha = Empresa::factory()->create();
        $outra = Empresa::factory()->create();

        $pedido = $this->pedidoDe($minha);
        $naFila = new PedidoEntrouNaFila($pedido);
        $atribuido = new PedidoAtribuido($pedido, de: null, automatico: true);

        foreach ([$naFila, $atribuido] as $evento) {
            $nomes = $this->nomes($evento->broadcastOn());

            $this->assertContains("private-empresa.{$minha->id}.central", $nomes);
            $this->assertNotContains("private-empresa.{$outra->id}.central", $nomes);
        }
    }

    /**
     * Guardião: todo canal de empresa é construído a partir de um `empresaId`,
     * nunca de uma constante.
     *
     * O defeito que isto impede é literal: `new PrivateChannel('empresa.1.pedidos')`
     * — que funcionaria perfeitamente em desenvolvimento com uma empresa só, e
     * mandaria os eventos de todas as revendas para o canal da primeira.
     *
     * É exatamente a classe de defeito que este plano inteiro persegue: correto
     * para uma revenda, catastrófico para N.
     */
    public function test_nenhum_canal_de_empresa_tem_id_constante(): void
    {
        $achados = [];
        $varridos = 0;

        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            $varridos++;
            $conteudo = (string) file_get_contents($arquivo->getPathname());

            // `empresa.` seguido de dígito é o defeito; seguido de `{` é
            // interpolação, que é o certo.
            if (preg_match_all('/[\'"](?:private-)?empresa\.\d/', $conteudo, $m)) {
                $achados[] = basename($arquivo->getPathname()).': '.implode(', ', $m[0]);
            }
        }

        $this->assertGreaterThan(200, $varridos, 'a varredura precisa ter varrido algo');
        $this->assertSame([], $achados, 'canal de empresa com id fixo manda o evento de todas para uma');
    }

    /**
     * O canal do entregador é por PEDIDO, não por empresa — e é o mais sensível
     * do sistema, porque carrega a posição de uma pessoa em tempo real.
     *
     * A autorização (`TempoRealTest`) já garante que só o cliente daquele pedido,
     * o entregador e o atendente entram. Aqui a prova é do outro lado: o evento
     * não nomeia o canal de outro pedido.
     */
    public function test_posicao_do_entregador_fica_no_canal_do_proprio_pedido(): void
    {
        $evento = new EntregadorPosicaoAtualizada(pedidoId: 42, latitude: -25.4, longitude: -51.4);

        $nomes = $this->nomes($evento->broadcastOn());

        $this->assertSame(['private-pedido.42.entregador'], $nomes);
    }

    /**
     * Reverb configurado explicitamente: sem app key, o tempo real não sobe.
     *
     * A verificação é do CONTRATO, não dos valores — o `.env` de produção não
     * está aqui. O que se protege é a existência das chaves que o servidor lê,
     * porque `null` silencioso ali derruba o WebSocket inteiro sem erro visível.
     */
    public function test_reverb_tem_as_chaves_que_o_servidor_le(): void
    {
        $this->assertSame('reverb', config('reverb.default'));

        $servidor = config('reverb.servers.reverb');

        $this->assertIsArray($servidor);
        foreach (['host', 'port'] as $chave) {
            $this->assertArrayHasKey($chave, $servidor, "reverb.servers.reverb.{$chave} precisa existir");
        }

        $apps = config('reverb.apps.apps');
        $this->assertIsArray($apps);
        $this->assertNotEmpty($apps, 'sem app declarada o Reverb recusa qualquer conexão');
    }
}
