<?php

namespace App\Models\Satelite;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A régua da vigilância, por empresa.
 *
 * Fica em tabela e não em constante porque calibrar limiar é trabalho de
 * operação, não de deploy: a revenda vai ajustar depois de ver os primeiros
 * alertas, e cada praça tem um ritmo de consumo.
 */
class ComodatoConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'comodato_config';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'dias_janela', 'giro_minimo', 'giro_critico',
        'queda_atencao', 'queda_critica', 'dias_sem_compra_alerta',
        'posse_minima_vigiada', 'dias_aviso_vencimento', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'dias_janela' => 'integer',
            'giro_minimo' => 'float',
            'giro_critico' => 'float',
            'queda_atencao' => 'float',
            'queda_critica' => 'float',
            'dias_sem_compra_alerta' => 'integer',
            'posse_minima_vigiada' => 'float',
            'dias_aviso_vencimento' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Config da empresa, ou os defaults.
     *
     * Devolve instância NÃO salva quando não existe registro: a vigilância tem
     * que funcionar em empresa que nunca abriu a tela de configuração, e criar
     * linha aqui faria um comando de leitura escrever no banco.
     */
    public static function daEmpresa(int $empresaId): self
    {
        $existente = static::query()->where('empresa_id', $empresaId)->first();

        if ($existente !== null) {
            return $existente;
        }

        return new self([
            'empresa_id' => $empresaId,
            'dias_janela' => 180,
            'giro_minimo' => 4,
            'giro_critico' => 1,
            'queda_atencao' => 40,
            'queda_critica' => 70,
            'dias_sem_compra_alerta' => 90,
            'posse_minima_vigiada' => 4,
            'dias_aviso_vencimento' => 30,
            'ativo' => true,
        ]);
    }
}
