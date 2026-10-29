<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Política de privacidade e exclusão de conta: URLs que a Play e a App Store
 * exigem. São anônimas e não dependem do banco (sob RLS, sem tenant, a tabela
 * `empresas` viria vazia) — os dados do controlador vêm da configuração.
 */
class PaginasPublicasTest extends TestCase
{
    private function configurar(): void
    {
        config()->set('paginas_publicas', [
            'app_nome' => 'Gás em Casa',
            'razao_social' => 'Revenda Exemplo Ltda',
            'cnpj' => '00.000.000/0001-00',
            'endereco' => 'Rua Um, 1',
            'telefone' => '(42) 3000-0000',
            'whatsapp' => null,
            'email' => 'privacidade@exemplo.com',
            'vigencia' => '2026-10-29',
        ]);
    }

    public function test_privacidade_e_publica_e_identifica_o_controlador(): void
    {
        $this->configurar();

        $this->get('/privacidade')
            ->assertOk()
            ->assertSee('Revenda Exemplo Ltda')
            ->assertSee('00.000.000/0001-00')
            ->assertSee('privacidade@exemplo.com')
            ->assertSee('29/10/2026')
            // Divulga o rastreamento do entregador em segundo plano (exigência da Play).
            ->assertSee('segundo plano');
    }

    public function test_exclusao_de_conta_explica_o_caminho_no_app_e_fora_dele(): void
    {
        $this->configurar();

        $this->get('/excluir-conta')
            ->assertOk()
            ->assertSee('Excluir conta')
            ->assertSee('Sem o aplicativo')
            ->assertSee('privacidade@exemplo.com');
    }

    public function test_sem_controlador_configurado_responde_404(): void
    {
        $this->configurar();
        config()->set('paginas_publicas.razao_social', null);

        $this->get('/privacidade')->assertNotFound();
        $this->get('/excluir-conta')->assertNotFound();
    }
}
