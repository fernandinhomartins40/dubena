<?php

namespace App\Models\Migracao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma execução da ferramenta de migração (SuperAdmin).
 *
 * Modelo GLOBAL (sem empresa_id): a migração roda antes de haver tenant e pode
 * criar empresas. As credenciais do banco de origem são de terceiros — ficam
 * cifradas em `config`, nunca em claro.
 */
class Migracao extends Model
{
    protected $table = 'migracoes';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_DIAGNOSTICANDO = 'diagnosticando';

    public const STATUS_AGUARDANDO_MAPEAMENTO = 'aguardando_mapeamento';

    public const STATUS_MIGRANDO = 'migrando';

    public const STATUS_CONCLUIDA = 'concluida';

    public const STATUS_FALHOU = 'falhou';

    protected $fillable = [
        'descricao', 'origem_tipo', 'config', 'status', 'diagnostico',
        'mapa_empresas', 'resultado', 'erro', 'progresso', 'etapa_atual',
        'platform_admin_id', 'iniciada_em', 'concluida_em',
    ];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'diagnostico' => 'array',
            'mapa_empresas' => 'array',
            'resultado' => 'array',
            'progresso' => 'integer',
            'iniciada_em' => 'datetime',
            'concluida_em' => 'datetime',
        ];
    }

    public function descartes(): HasMany
    {
        return $this->hasMany(MigracaoDescarte::class);
    }

    public function emAndamento(): bool
    {
        return in_array($this->status, [
            self::STATUS_DIAGNOSTICANDO,
            self::STATUS_MIGRANDO,
        ], true);
    }
}
