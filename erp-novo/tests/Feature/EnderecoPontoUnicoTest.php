<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3-01 — o endereço tem um ponto único, e ninguém monta a string à mão.
 *
 * `Cliente::endereco_completo` já existia, com um comentário que registra a
 * medição: **a coluna `endereco` está NULL em 100% da base** (0 de 55.453
 * medidos em produção). O logradouro real sempre veio da FK `rua_id`.
 *
 * Mesmo assim, quatro lugares continuavam montando a string à mão a partir da
 * coluna — e como ela é nula, todos exibiam **só o número**:
 *
 *  - o cupom fiscal;
 *  - o contrato de comodato, cujo propósito é localizar quem está com o
 *    vasilhame;
 *  - a central de vendas;
 *  - o app do entregador, que serve justamente para ele CHEGAR no endereço.
 *
 * "Endereço: 587" não é um erro que trava nada: é um documento entregue ao
 * cliente com a informação errada, e ninguém liga o sintoma à causa.
 */
class EnderecoPontoUnicoTest extends TestCase
{
    use RefreshDatabase;

    private function clienteComEnderecoNaFk(): Cliente
    {
        $empresa = Empresa::factory()->create();

        Estado::query()->firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná', 'cod_ibge' => 41]);
        $cidade = Cidade::query()->create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Guarapuava', 'uf' => 'PR', 'ativo' => true,
        ]);
        $bairro = Bairro::query()->create([
            'grupo_id' => $empresa->grupo_id, 'cidade_id' => $cidade->id,
            'descricao' => 'Centro', 'ativo' => true,
        ]);
        $rua = Rua::query()->create([
            'grupo_id' => $empresa->grupo_id, 'cidade_id' => $cidade->id, 'bairro_id' => $bairro->id,
            'descricao' => 'Rua XV de Novembro', 'ativo' => true,
        ]);

        return Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'Fulano',
            // Como na base real: a coluna de texto vazia, o logradouro na FK.
            'endereco' => null,
            'numero' => '587',
            'rua_id' => $rua->id,
            'bairro_id' => $bairro->id,
            'cidade_id' => $cidade->id,
        ]);
    }

    /** O accessor resolve pela FK quando o texto está vazio — que é o caso real. */
    public function test_endereco_completo_vem_da_fk_quando_o_texto_e_nulo(): void
    {
        $cliente = $this->clienteComEnderecoNaFk();

        $this->assertStringContainsString('Rua XV de Novembro', $cliente->endereco_completo);
        $this->assertStringContainsString('587', $cliente->endereco_completo);
        $this->assertStringContainsString('Centro', $cliente->endereco_completo);
    }

    /** A linha compacta (cupom, etiqueta) também precisa do logradouro. */
    public function test_endereco_linha_tambem_resolve_pela_fk(): void
    {
        $cliente = $this->clienteComEnderecoNaFk();

        $this->assertSame('Rua XV de Novembro 587', $cliente->endereco_linha);
    }

    /** Texto preenchido continua vencendo — cadastro antigo não pode regredir. */
    public function test_texto_preenchido_vence_a_fk(): void
    {
        $cliente = $this->clienteComEnderecoNaFk();
        $cliente->update(['endereco' => 'Avenida importada do legado']);

        $this->assertStringContainsString('Avenida importada do legado', $cliente->fresh()->endereco_completo);
    }

    /**
     * O guardião: ninguém volta a montar a string a partir da coluna.
     *
     * A varredura procura a leitura direta de `->endereco` num contexto de
     * exibição. O acessor tem nome diferente (`endereco_completo`,
     * `endereco_linha`), então o padrão proibido é inequívoco.
     */
    public function test_ninguem_monta_o_endereco_a_mao(): void
    {
        $achados = [];
        $varridos = 0;

        foreach ([app_path('Domain'), app_path('Http')] as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($it as $arquivo) {
                if (! $arquivo->isFile() || $arquivo->getExtension() !== 'php') {
                    continue;
                }

                $varridos++;

                // `IdentidadeCliente` usa o logradouro como TRACO de
                // identidade para deduplicacao — separado do numero e da
                // cidade, e nao como string para exibir. Ele ja resolve a FK
                // corretamente; forcar o accessor ali juntaria numero e cidade
                // no mesmo traco e faria dois clientes distintos casarem.
                if (basename($arquivo->getPathname()) === 'IdentidadeCliente.php') {
                    continue;
                }

                foreach (file($arquivo->getPathname()) as $n => $linha) {
                    $limpa = ltrim($linha);
                    if (str_starts_with($limpa, '//') || str_starts_with($limpa, '*')) {
                        continue;
                    }

                    // `cliente?->endereco` seguido de `??` ou concatenação é a
                    // montagem manual. `endereco_completo`/`endereco_linha` e
                    // `endereco_id` não casam, porque o `\b` fecha a palavra.
                    if (preg_match('/cliente\??->endereco\b(?!_)/', $linha) === 1) {
                        $achados[] = basename($arquivo->getPathname()).':'.($n + 1);
                    }
                }
            }
        }

        // Um guardião que varre zero arquivos passa sempre — e isso já
        // aconteceu neste repositório (ver FkTenantAwareTest).
        $this->assertGreaterThan(50, $varridos, 'a varredura não alcançou os arquivos');

        $this->assertSame([], $achados, implode("\n", array_merge(
            ['Endereço montado à mão a partir da coluna (que é NULL em toda a base):'],
            $achados,
            ['', 'Use `$cliente->endereco_completo` ou `->endereco_linha`.'],
        )));
    }
}
