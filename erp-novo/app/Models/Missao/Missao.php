<?php

namespace App\Models\Missao;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Monitora\Cerca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Missão de campo (L7) — o MOLDE: tipo, área (cerca poligonal OU centro+raio),
 * meta, janela e exigência de foto. A execução por entregador vive em
 * MissaoAtribuicao. Tenant-scoped.
 */
class Missao extends Model
{
    use BelongsToTenant;

    protected $table = 'missoes';

    public const TIPOS = [
        'panfletagem', 'visita_comercial', 'divulgacao_valegas',
        'prospeccao', 'acao_promocional', 'campanha_bairro',
    ];

    protected $fillable = [
        'empresa_id', 'grupo_id', 'tipo', 'titulo', 'descricao',
        'cerca_id', 'centro_lat', 'centro_lng', 'raio_m',
        'meta_visitas', 'janela_inicio', 'janela_fim', 'exige_foto', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'centro_lat' => 'decimal:7',
            'centro_lng' => 'decimal:7',
            'raio_m' => 'integer',
            'meta_visitas' => 'integer',
            'exige_foto' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function cerca(): BelongsTo
    {
        return $this->belongsTo(Cerca::class, 'cerca_id');
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(MissaoAtribuicao::class, 'missao_id');
    }

    /** A janela horária permite executar agora? Sem janela = sempre. */
    public function dentroDaJanela(?\DateTimeInterface $quando = null): bool
    {
        if (! $this->janela_inicio || ! $this->janela_fim) {
            return true;
        }
        $hora = ($quando ?? now())->format('H:i:s');

        return $hora >= $this->janela_inicio && $hora <= $this->janela_fim;
    }
}
