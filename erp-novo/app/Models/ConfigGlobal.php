<?php

namespace App\Models;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração global por GRUPO (F01) — RT/CSRT, SMTP, SAT, Google Maps.
 * Uma linha por grupo (escopada por BelongsToGrupo). Segredos criptografados.
 */
class ConfigGlobal extends Model
{
    use BelongsToGrupo;

    protected $table = 'config_globais';

    protected $fillable = [
        'grupo_id',
        'rt_cnpj', 'rt_contato', 'rt_email', 'rt_telefone', 'rt_id_csrt', 'rt_csrt',
        'email_remetente', 'email_nome_remetente', 'email_host', 'email_porta',
        'email_usuario', 'email_senha', 'email_tls',
        'sat_cnpj_prod', 'sat_cnpj_homolog', 'sat_signac_prod', 'sat_signac_homolog',
        'google_maps_key', 'link_monitoramento',
    ];

    protected $hidden = ['rt_csrt', 'email_senha', 'sat_signac_prod', 'sat_signac_homolog'];

    protected function casts(): array
    {
        return [
            'email_porta' => 'integer',
            'email_tls' => 'boolean',
            'rt_csrt' => 'encrypted',
            'email_senha' => 'encrypted',
            'sat_signac_prod' => 'encrypted',
            'sat_signac_homolog' => 'encrypted',
        ];
    }
}
