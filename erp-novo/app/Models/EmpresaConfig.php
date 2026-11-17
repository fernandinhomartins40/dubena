<?php

namespace App\Models;

use App\Domain\Shared\Auditavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuração operacional/fiscal da empresa (1:1 com Empresa).
 * Senha de e-mail encriptada; senha-mestra é hash (setada via service, fora do fill).
 * `dados` (JSON) guarda as demais chaves de config promovidas a coluna conforme as fases.
 */
class EmpresaConfig extends Model
{
    use Auditavel;
    use HasFactory;

    protected $table = 'empresa_configs';

    protected $fillable = [
        'empresa_id',
        'tempoentrega', 'tempourgente', 'maximoparcelas',
        'email_host', 'email_port', 'email_username', 'email_password',
        'email_from_address', 'email_from_name', 'email_encryption',
        'cert_path', 'cert_senha', 'cert_cnpj', 'cert_titular',
        'cert_validade', 'cert_uploaded_at',
        'nfce_csc_id', 'nfce_csc_token',
        'dados',
    ];

    protected $hidden = ['senha_mestra', 'email_password', 'cert_senha', 'cert_path', 'nfce_csc_token'];

    protected function casts(): array
    {
        return [
            'tempoentrega' => 'integer',
            'tempourgente' => 'integer',
            'maximoparcelas' => 'integer',
            'email_port' => 'integer',
            'email_password' => 'encrypted',
            'cert_senha' => 'encrypted',
            'cert_validade' => 'datetime',
            'cert_uploaded_at' => 'datetime',
            'nfce_csc_token' => 'encrypted',
            'dados' => 'array',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * PIE R3-F-01 — a linha nasce com a chave de tenant do envelope.
     *
     * Sem ela a policy (`app_tenant_can_operate(tenant_account_id, …)`) recusava
     * o insert e toda revenda nova levava 500 ao abrir ou salvar a configuração
     * da empresa. A chave nunca vem do payload (fora do `$fillable`).
     */
    protected static function booted(): void
    {
        static::creating(function (self $config): void {
            if (! empty($config->getAttribute('tenant_account_id'))) {
                return;
            }
            $tenant = app(\App\Domain\Tenant\TenantEnvelopeRuntime::class)->current()?->tenantAccountId;
            if ($tenant !== null && $tenant > 0) {
                $config->setAttribute('tenant_account_id', $tenant);
            }
        });
    }
}
