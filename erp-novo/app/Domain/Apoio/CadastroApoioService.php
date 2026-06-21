<?php

namespace App\Domain\Apoio;

use App\Models\Apoio\CadastroApoio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Serviço genérico dos cadastros de apoio. Regra de negócio pura (sem HTTP):
 * o controller valida e chama estes métodos. O escopo por grupo é automático
 * (trait BelongsToGrupo) — aqui só tratamos a unicidade de descrição e o CRUD.
 */
class CadastroApoioService
{
    public function __construct(private CadastroApoioRegistry $registry)
    {
    }

    /** @return Collection<int, CadastroApoio> */
    public function listar(string $tipo, ?string $busca = null): Collection
    {
        $model = $this->registry->config($tipo)['model'];

        return $model::query()
            ->when($busca, fn ($q) => $q->where('descricao', 'ilike', '%'.$busca.'%'))
            ->orderBy('descricao')
            ->get();
    }

    /** @param array<string, mixed> $dados */
    public function criar(string $tipo, array $dados): CadastroApoio
    {
        $model = $this->registry->config($tipo)['model'];
        $this->garantirDescricaoUnica($tipo, $dados['descricao']);

        return $model::create($this->normalizar($dados));
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(string $tipo, int $id, array $dados): CadastroApoio
    {
        $model = $this->registry->config($tipo)['model'];
        $registro = $model::query()->findOrFail($id);
        $this->garantirDescricaoUnica($tipo, $dados['descricao'], $id);

        $registro->update($this->normalizar($dados));

        return $registro->refresh();
    }

    public function excluir(string $tipo, int $id): void
    {
        $model = $this->registry->config($tipo)['model'];
        $model::query()->findOrFail($id)->delete();
    }

    /**
     * Mantém só descricao/ativo + os extras declarados (o $fillable de cada model
     * já protege, mas aqui garantimos o default de `ativo`).
     *
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private function normalizar(array $dados): array
    {
        $dados['ativo'] = array_key_exists('ativo', $dados) ? (bool) $dados['ativo'] : true;

        return $dados;
    }

    /** Unicidade de descrição dentro do grupo (case-insensitive). */
    private function garantirDescricaoUnica(string $tipo, string $descricao, ?int $ignorarId = null): void
    {
        $model = $this->registry->config($tipo)['model'];

        $existe = $model::query()
            ->whereRaw('lower(descricao) = ?', [mb_strtolower($descricao)])
            ->when($ignorarId, fn ($q) => $q->where('id', '<>', $ignorarId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'descricao' => 'Já existe um registro com essa descrição.',
            ]);
        }
    }
}
