<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro da trilha de auditoria (F11). Imutável (sem updated_at): cada mudança
 * gera uma linha. Escopo por empresa via coluna empresa_id (filtrado no relatório).
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'empresa_id', 'entidade', 'entidade_id', 'acao', 'user_id',
        'antes', 'depois', 'ip', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'depois' => 'array',
            'criado_em' => 'datetime',
        ];
    }
}
