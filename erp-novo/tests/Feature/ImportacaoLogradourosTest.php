<?php

namespace Tests\Feature;

use App\Domain\Geografico\Contracts\FonteLogradouros;
use App\Domain\Geografico\Drivers\FonteLogradourosFake;
use App\Domain\Geografico\Drivers\ViaCepFonte;
use App\Domain\Geografico\ImportarLogradouros;
use App\Domain\Geografico\ImportarLogradourosJob;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\ImportacaoLogradouro;
use App\Models\Geografico\Rua;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Importação de logradouros a partir da base de CEP.
 *
 * O teste central é o do TETO: a fonte trunca a resposta sem avisar, e uma
 * varredura que não refina perde ruas silenciosamente. Esse é o modo de falha
 * que a importação existe para evitar.
 */
class ImportacaoLogradourosTest extends TestCase
{
    use RefreshDatabase;

    private function cidade(): Cidade
    {
        Estado::firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná', 'cod_ibge' => 41]);
        $empresa = Empresa::factory()->create();

        return Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Guarapuava',
            'uf' => 'PR',
        ]);
    }

    /** Usuário do mesmo grupo da cidade, para as chamadas de API. */
    private function usuarioDoGrupo(Cidade $cidade): User
    {
        $empresa = Empresa::factory()->create(['grupo_id' => $cidade->grupo_id]);

        return User::factory()->create([
            'grupo_id' => $cidade->grupo_id,
            'empresa_id' => $empresa->id,
            'support' => true,
        ]);
    }

    /** @return list<array{logradouro:string, bairro:string, cep:string}> */
    private function acervo(int $quantidade, string $prefixo = 'Rua Santos'): array
    {
        $itens = [];
        for ($i = 1; $i <= $quantidade; $i++) {
            $itens[] = [
                'logradouro' => $prefixo.' '.$i,
                'bairro' => 'Centro',
                'cep' => sprintf('85010-%03d', $i),
            ];
        }

        return $itens;
    }

    public function test_importa_ruas_e_bairros_da_fonte(): void
    {
        $cidade = $this->cidade();

        $fonte = new FonteLogradourosFake([
            ['logradouro' => 'Rua Sao Joao', 'bairro' => 'Centro', 'cep' => '85010-001'],
            ['logradouro' => 'Avenida Santana', 'bairro' => 'Santana', 'cep' => '85070-010'],
        ]);

        $importador = new ImportarLogradouros($fonte);
        $r = $importador->varrer('PR', 'Guarapuava');
        $g = $importador->gravar($cidade, $r['logradouros']);

        $this->assertSame(2, $g['ruas_criadas']);
        $this->assertSame(2, $g['bairros_criados']);

        // A rua nasce JÁ ligada ao bairro — é o vínculo que o schema novo tinha
        // perdido e sem o qual não se preenche o bairro a partir da rua.
        $rua = Rua::withoutGrupo()->where('descricao', 'Avenida Santana')->first();
        $this->assertNotNull($rua->bairro_id);
        $this->assertSame('Santana', Bairro::withoutGrupo()->find($rua->bairro_id)->descricao);
        $this->assertSame('85070-010', $rua->cep);
    }

    public function test_refina_a_busca_quando_bate_o_teto_da_fonte(): void
    {
        $cidade = $this->cidade();

        // 12 ruas com teto de 5: uma varredura sem refino veria no máximo 5 e
        // acharia que terminou. É exatamente o modo de falha da API real.
        $fonte = new FonteLogradourosFake($this->acervo(12), teto: 5);

        $importador = new ImportarLogradouros($fonte);
        $r = $importador->varrer('PR', 'Guarapuava');

        $this->assertGreaterThan(5, count($r['logradouros']), 'O refino deveria ter passado do teto da fonte.');

        $g = $importador->gravar($cidade, $r['logradouros']);
        $this->assertGreaterThan(5, $g['ruas_criadas']);
    }

    public function test_nao_duplica_ao_reimportar(): void
    {
        $cidade = $this->cidade();

        $fonte = new FonteLogradourosFake([
            ['logradouro' => 'Rua Sao Joao', 'bairro' => 'Centro', 'cep' => '85010-001'],
        ]);

        $importador = new ImportarLogradouros($fonte);
        $r = $importador->varrer('PR', 'Guarapuava');

        $importador->gravar($cidade, $r['logradouros']);
        $segunda = $importador->gravar($cidade, $r['logradouros']);

        $this->assertSame(0, $segunda['ruas_criadas']);
        $this->assertSame(0, $segunda['bairros_criados']);
        $this->assertSame(1, Rua::withoutGrupo()->where('cidade_id', $cidade->id)->count());
    }

    public function test_completa_rua_existente_sem_trocar_o_id(): void
    {
        $cidade = $this->cidade();

        // 44.338 clientes apontam para ruas.id: recriar a rua apagaria o
        // endereço deles. A importação tem que COMPLETAR, nunca substituir.
        $existente = Rua::create([
            'grupo_id' => $cidade->grupo_id,
            'cidade_id' => $cidade->id,
            'descricao' => 'RUA SAO JOAO',
            'ativo' => true,
        ]);

        $fonte = new FonteLogradourosFake([
            ['logradouro' => 'Rua Sao Joao', 'bairro' => 'Centro', 'cep' => '85010-001'],
        ]);

        $importador = new ImportarLogradouros($fonte);
        $r = $importador->varrer('PR', 'Guarapuava');
        $g = $importador->gravar($cidade, $r['logradouros']);

        $this->assertSame(0, $g['ruas_criadas']);
        $this->assertSame(1, $g['ruas_atualizadas']);

        $existente->refresh();
        $this->assertSame('RUA SAO JOAO', $existente->descricao, 'O nome digitado pelo operador não pode ser sobrescrito.');
        $this->assertSame('85010-001', $existente->cep);
        $this->assertNotNull($existente->bairro_id);
    }

    public function test_endpoint_dispara_importacao_em_fila(): void
    {
        Queue::fake();

        $cidade = $this->cidade();
        $user = $this->usuarioDoGrupo($cidade);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/logradouros/importacoes', ['cidade_id' => $cidade->id])
            ->assertStatus(202)
            ->assertJsonPath('data.situacao', 'processando');

        Queue::assertPushed(ImportarLogradourosJob::class);
    }

    public function test_recusa_segunda_importacao_simultanea_da_mesma_cidade(): void
    {
        Queue::fake();

        $cidade = $this->cidade();
        $user = $this->usuarioDoGrupo($cidade);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/logradouros/importacoes', ['cidade_id' => $cidade->id])
            ->assertStatus(202);

        // A varredura custa centenas de requisições: disparar a segunda só
        // duplicaria o custo para chegar ao mesmo resultado.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/logradouros/importacoes', ['cidade_id' => $cidade->id])
            ->assertStatus(409);
    }

    public function test_falha_da_fonte_so_marca_falhou_na_ultima_tentativa(): void
    {
        // Este teste exercita a RETENTATIVA da fila, não a fronteira de tenant.
        // Com o enforcement ligado o job exige envelope e lança antes de chegar
        // ao ponto testado, então o modo é fixado aqui.
        config()->set('saas_transformation.enforcement.tenant_envelope', false);

        $cidade = $this->cidade();

        $registro = ImportacaoLogradouro::create([
            'grupo_id' => $cidade->grupo_id,
            'cidade_id' => $cidade->id,
            'fonte' => 'viacep',
            'situacao' => 'processando',
        ]);

        $importador = $this->mock(ImportarLogradouros::class);
        $importador->shouldReceive('varrer')->andThrow(new \RuntimeException('rede caiu'));

        // 1ª tentativa: o registro TEM de continuar 'processando', senão a
        // guarda do handle aborta a retentativa e o $tries = 2 vira decoração.
        $job = new ImportarLogradourosJob($registro->id);
        $job->job = $this->tentativa(1);

        try {
            $job->handle($importador);
        } catch (\RuntimeException) {
            // Relançar é o que faz a fila reagendar.
        }

        $this->assertSame('processando', $registro->refresh()->situacao);

        // Última tentativa: aí sim registra a falha, senão a tela mostraria
        // "processando" para sempre.
        $job->job = $this->tentativa(2);

        try {
            $job->handle($importador);
        } catch (\RuntimeException) {
        }

        $this->assertSame('falhou', $registro->refresh()->situacao);
        $this->assertStringContainsString('rede caiu', (string) $registro->erro);
    }

    /** Job da fila fingindo estar na N-ésima tentativa. */
    private function tentativa(int $n): Job
    {
        $fake = \Mockery::mock(Job::class);
        $fake->shouldReceive('attempts')->andReturn($n);
        $fake->shouldReceive('getConnectionName')->andReturn('sync');

        return $fake;
    }

    public function test_fonte_ignora_termo_curto_demais_para_a_api(): void
    {
        // A API real responde HTTP 400 abaixo de 3 caracteres; gastar a
        // requisição para descobrir isso seria desperdício em massa.
        $fonte = new FonteLogradourosFake($this->acervo(3));

        $this->assertSame([], $fonte->buscar('PR', 'Guarapuava', 'ab'));
    }

    public function test_driver_real_e_o_default_do_container(): void
    {
        // Não usa credencial, então o driver real é o default — ao contrário dos
        // gates pagos, que caem no Fake sem chave.
        $this->assertInstanceOf(
            ViaCepFonte::class,
            app(FonteLogradouros::class),
        );
    }
}
