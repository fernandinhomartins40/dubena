<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_id',
        'grupo_id',
        'support',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'support' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** Empresas que o usuário pode acessar (multi-empresa). */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_user');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('empresa_id');
    }

    // ---- Tenant (usado por ResolveTenant) ----

    /** O usuário pode operar nesta empresa? (própria, ou nas permitidas). */
    public function podeAcessarEmpresa(int $empresaId): bool
    {
        if ($this->support) {
            return true; // suporte = acesso total (regra do legado)
        }

        return (int) $this->empresa_id === $empresaId
            || $this->empresas()->whereKey($empresaId)->exists();
    }

    /** Grupo da empresa informada (para setar o tenant ao trocar de empresa). */
    public function grupoIdDaEmpresa(int $empresaId): ?int
    {
        $empresa = Empresa::query()->find($empresaId);

        return $empresa?->grupo_id;
    }

    // ---- RBAC (substitui menuusers + spatie) ----

    /**
     * Tem a permissão "modulo.acao" na empresa ativa?
     * Suporte = bypass (regra do legado: support=1 ignora permissões).
     */
    public function temPermissao(string $chave, ?int $empresaId = null): bool
    {
        if ($this->support) {
            return true;
        }

        $empresaId ??= $this->empresa_id;

        return $this->roles()
            ->wherePivot('empresa_id', $empresaId)
            ->whereHas('permissions', fn ($q) => $q->where('chave', $chave))
            ->exists()
            // papéis globais (sem empresa específica) também valem
            || $this->roles()
                ->wherePivotNull('empresa_id')
                ->whereHas('permissions', fn ($q) => $q->where('chave', $chave))
                ->exists();
    }
}
