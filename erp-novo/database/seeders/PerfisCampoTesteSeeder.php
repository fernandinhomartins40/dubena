<?php

namespace Database\Seeders;

use App\Domain\Rh\ModoEstoque;
use App\Domain\Rh\VinculoColaborador;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorComissao;
use App\Models\Satelite\ValeGas;
use App\Models\User;
use App\Models\Venda\AlcadaDesconto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuários de teste dos TRÊS perfis de campo — para ver o app se comportando
 * com cada nível de acesso.
 *
 * O papel do token sai do VÍNCULO do colaborador (`AppAuthController::abilitiesDe`),
 * então não basta criar `users`: sem o `colaborador` correspondente todos caem em
 * `funcionario`, e os três botões do app mostrariam a mesma coisa.
 *
 * Cada perfil também recebe o que o torna distinguível na prática:
 *  - **funcionário**: entrega, sem alçada (não concede desconto);
 *  - **franqueado**: alçada de 5%, consignação e comissão mista (repasse+percentual);
 *  - **industrial**: alçada de 10% e acesso à emissão de NF-e em campo.
 *
 * Idempotente: roda quantas vezes precisar sem duplicar.
 *
 * ```
 * php artisan db:seed --class=PerfisCampoTesteSeeder
 * ```
 */
class PerfisCampoTesteSeeder extends Seeder
{
    /**
     * Senha FIXA de propósito: é o que permite testar o login pelo app sem
     * combinar credencial a cada rodada.
     *
     * Por isso mesmo, este seeder é bloqueado em produção (F7-11) — senha
     * conhecida num ambiente real é conta aberta, e `db:seed --class=` não
     * pergunta em que ambiente está.
     */
    private const SENHA = 'teste123';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'PerfisCampoTesteSeeder é fixture de teste e cria usuários com senha conhecida. '
                .'Não pode rodar em produção.'
            );
        }

        $empresa = Empresa::query()->orderBy('id')->first();

        if ($empresa === null) {
            $this->command?->warn('Nenhuma empresa cadastrada — rode o seed principal antes.');

            return;
        }

        $perfis = [
            [
                'email' => 'entregador@teste.com',
                'nome' => 'Edu Entregador (CLT)',
                'vinculo' => VinculoColaborador::FUNCIONARIO,
                'modo' => null,          // funcionário não carrega estoque próprio
                'alcada' => null,        // e não concede desconto
                'comissao' => null,
            ],
            [
                'email' => 'franqueado@teste.com',
                'nome' => 'Fabio Franqueado (PJ)',
                'vinculo' => VinculoColaborador::FRANQUEADO,
                'modo' => ModoEstoque::CONSIGNACAO,
                'alcada' => 5.0,
                // Misto: a empresa retém 70/un e ele ainda leva 3% sobre a venda.
                'comissao' => ['tipo' => 3, 'empresa_valor' => 70, 'percentual' => 3],
            ],
            [
                'email' => 'industrial@teste.com',
                'nome' => 'Ivo Industrial',
                'vinculo' => VinculoColaborador::INDUSTRIAL,
                'modo' => ModoEstoque::COMPRA,
                'alcada' => 10.0,        // negocia com empresa: teto maior
                'comissao' => ['tipo' => 1, 'empresa_valor' => 0, 'percentual' => 5],
            ],
        ];

        foreach ($perfis as $p) {
            $user = User::query()->updateOrCreate(
                ['email' => $p['email']],
                [
                    'name' => $p['nome'],
                    'password' => Hash::make(self::SENHA),
                    'empresa_id' => $empresa->id,
                    'grupo_id' => $empresa->grupo_id,
                    'ativo' => true,
                ],
            );

            $colaborador = Colaborador::withoutTenant()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'empresa_id' => $empresa->id,
                    'grupo_id' => $empresa->grupo_id,
                    'nome' => $p['nome'],
                    'vinculo' => $p['vinculo']->value,
                    'modo_estoque' => $p['modo']?->value,
                    'entregador' => $p['vinculo'] !== VinculoColaborador::INDUSTRIAL,
                    'ativo' => true,
                ],
            );

            if ($p['alcada'] !== null) {
                AlcadaDesconto::withoutTenant()->updateOrCreate(
                    ['empresa_id' => $empresa->id, 'colaborador_id' => $colaborador->id],
                    ['percentual_max' => $p['alcada'], 'base_calculo' => 'tabela', 'permite_solicitar' => true, 'ativo' => true],
                );
            }

            if ($p['comissao'] !== null) {
                ColaboradorComissao::withoutTenant()->updateOrCreate(
                    ['empresa_id' => $empresa->id, 'colaborador_id' => $colaborador->id, 'produto_id' => null],
                    [
                        'tipo_comissao' => $p['comissao']['tipo'],
                        'empresa_valor' => $p['comissao']['empresa_valor'],
                        'percentual' => $p['comissao']['percentual'],
                        'ativo' => true,
                    ],
                );
            }

            $this->command?->info("  {$p['email']} — {$p['vinculo']->value}");

            $this->darTrabalho($empresa, $user, $colaborador, $p['vinculo']);
        }

        $this->valesDeTeste($empresa);

        $this->command?->info('Senha dos três: '.self::SENHA);
        $this->command?->info('Vales de teste: TESTE01 a TESTE05 (o TESTE05 já vem UTILIZADO, para ver a recusa)');
    }

    /**
     * Sem pedido atribuído, as telas de entrega e o relatório abrem vazios — e
     * quem testa conclui que a função está quebrada. Aqui cada perfil recebe
     * trabalho de verdade:
     *  - um pedido PENDENTE atribuído (aparece em "Entregas");
     *  - um pedido CONCLUÍDO (alimenta "Minhas vendas" e o extrato de comissão).
     */
    private function darTrabalho(Empresa $empresa, User $user, Colaborador $colaborador, VinculoColaborador $vinculo): void
    {
        $pendente = PedidoSituacao::query()->where('grupo_id', $empresa->grupo_id)
            ->where('efeito', 'PENDENTE')->where('ativo', true)->orderBy('ordem')->first();
        $concluido = PedidoSituacao::query()->where('grupo_id', $empresa->grupo_id)
            ->where('efeito', 'CONCLUIDO')->where('ativo', true)->orderBy('ordem')->first();

        $cliente = Cliente::withoutTenant()->where('empresa_id', $empresa->id)->where('ativo', true)->first();
        $setor = Setor::withoutTenant()->where('empresa_id', $empresa->id)->first();
        $produto = Produto::withoutTenant()->where('empresa_id', $empresa->id)->where('ativo', true)
            ->where('preco_venda', '>', 0)->first();

        if (! $pendente || ! $concluido || ! $cliente || ! $setor || ! $produto) {
            $this->command?->warn('    (sem cliente/produto/situação na base — pulei os pedidos)');

            return;
        }

        // Marca no observacao para reconhecer (e poder limpar) o que é de teste.
        $marca = 'PEDIDO DE TESTE — '.$vinculo->value;

        if (Pedido::withoutTenant()->where('observacao', $marca)->exists()) {
            return;   // idempotente: não empilha pedido a cada execução
        }

        foreach ([[$pendente, false], [$concluido, true]] as [$situacao, $concretiza]) {
            $pedido = Pedido::withoutTenant()->create([
                'empresa_id' => $empresa->id,
                'grupo_id' => $empresa->grupo_id,
                'cliente_id' => $cliente->id,
                'setor_id' => $setor->id,
                'pedidosituacao_id' => $situacao->id,
                'entregador_user_id' => $user->id,
                'atendente_user_id' => $user->id,
                'datahora' => now(),
                'valor_venda' => (float) $produto->preco_venda * 2,
                'valor_desconto' => 0,
                // `estoque_movimentado` direto: o objetivo é ter dado para a tela
                // ler, não exercitar a máquina de estados (que os testes já cobrem).
                'estoque_movimentado' => $concretiza,
                'observacao' => $marca,
            ]);

            $pedido->itens()->create([
                'produto_id' => $produto->id,
                'quantidade' => 2,
                'preco_unitario' => (float) $produto->preco_venda,
                'desconto' => 0,
                'valor_total' => (float) $produto->preco_venda * 2,
            ]);
        }

        $this->command?->info('    + 1 pedido pendente e 1 concluído');
    }

    /**
     * Vales com código MEMORIZÁVEL.
     *
     * A base tem 23 mil vales reais, mas ninguém consegue testar sem saber um
     * código válido — foi por isso que a função pareceu quebrada. `TESTE05` vem
     * UTILIZADO de propósito, para conferir a mensagem de recusa.
     */
    private function valesDeTeste(Empresa $empresa): void
    {
        $produto = Produto::withoutTenant()->where('empresa_id', $empresa->id)->where('ativo', true)->first();

        foreach (range(1, 5) as $n) {
            $codigo = sprintf('TESTE%02d', $n);
            $usado = $n === 5;

            ValeGas::withoutTenant()->updateOrCreate(
                ['codigo' => $codigo],
                [
                    'empresa_id' => $empresa->id,
                    'grupo_id' => $empresa->grupo_id,
                    'produto_id' => $produto?->id,
                    'valor' => 100.00,
                    'validade' => now()->addYear()->toDateString(),
                    'situacao' => $usado ? 'UTILIZADO' : 'EMITIDO',
                    'utilizado_em' => $usado ? now() : null,
                ],
            );
        }
    }
}
