<?php

namespace Database\Seeders;

use App\Domain\Rh\ModoEstoque;
use App\Domain\Rh\VinculoColaborador;
use App\Models\Empresa;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorComissao;
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
    private const SENHA = 'teste123';

    public function run(): void
    {
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
        }

        $this->command?->info('Senha dos três: '.self::SENHA);
    }
}
