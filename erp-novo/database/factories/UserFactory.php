<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $empresa = Empresa::factory();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'empresa_id' => $empresa,
            'grupo_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])?->grupo_id,
            'support' => false,
            'ativo' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Todo usuario de teste nasce com membership ativa e grant na sua empresa.
     *
     * Com o enforcement SaaS ligado, `empresa_id` no `users` nao autoriza nada:
     * quem autoriza e o grant aprovado. Sem isto as fixtures legadas nao passam
     * pelo resolver.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->empresa_id === null) {
                return; // usuario de plataforma: fronteira e outra
            }

            FronteiraTenant::paraUsuario($user);
        });
    }

    /**
     * Usuario sem fronteira SaaS, para exercitar a negacao do resolver.
     *
     * Limpa tambem a fronteira da empresa que a propria factory criou: o
     * `definition()` instancia `Empresa::factory()`, que ja nasce com tenant e
     * vinculo. Sem isto os testes que contam `tenant_accounts` para provar que
     * nada e inferido encontrariam a conta dessa empresa implicita.
     */
    public function semFronteiraSaas(): static
    {
        return $this->afterCreating(function (User $user): void {
            TenantMembership::query()->where('user_id', $user->id)->delete();

            $empresa = $user->empresa_id !== null
                ? Empresa::withoutGlobalScopes()->find($user->empresa_id)
                : null;
            if ($empresa !== null) {
                Empresa::factory()->semFronteiraSaas()->callAfterCreating(collect([$empresa]));
            }
        });
    }
}
