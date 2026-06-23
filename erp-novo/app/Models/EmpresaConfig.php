<?php

namespace App\Models;

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
}
