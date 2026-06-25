<?php

namespace App\Domain\Shared;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Trait de AUDITORIA (F11): registra em `audit_logs` cada created/updated/deleted
 * do model — quem, quando, empresa, valores antes/depois e IP. Substitui o
 * `revisions` do legado de forma unificada.
 *
 * Models sensíveis declaram `use Auditavel;`. Campos podem ser ocultados da trilha
 * via `protected array $auditExcluir = [...]` (ex.: segredos, timestamps).
 */
trait Auditavel
{
    public static function bootAuditavel(): void
    {
        static::created(fn (Model $m) => $m->registrarAuditoria('criado', null, $m->getAttributes()));

        static::updated(function (Model $m) {
            $alterados = $m->getChanges();
            unset($alterados['updated_at']);
            if ($alterados === []) {
                return;
            }
            $antes = array_intersect_key($m->getOriginal(), $alterados);
            $m->registrarAuditoria('atualizado', $antes, $alterados);
        });

        static::deleted(fn (Model $m) => $m->registrarAuditoria('excluido', $m->getOriginal(), null));
    }

    /**
     * @param  array<string,mixed>|null  $antes
     * @param  array<string,mixed>|null  $depois
     */
    public function registrarAuditoria(string $acao, ?array $antes, ?array $depois): void
    {
        $excluir = array_merge(
            ['created_at', 'updated_at'],
            property_exists($this, 'auditExcluir') ? $this->auditExcluir : [],
            // nunca registra valores de campos criptografados (segredos).
            method_exists($this, 'getCasts')
                ? array_keys(array_filter($this->getCasts(), fn ($c) => $c === 'encrypted'))
                : [],
        );

        AuditLog::create([
            'empresa_id' => $this->getAttribute('empresa_id'),
            'entidade' => $this->getTable(),
            'entidade_id' => $this->getKey(),
            'acao' => $acao,
            'user_id' => Auth::id(),
            'antes' => $antes !== null ? $this->limpar($antes, $excluir) : null,
            'depois' => $depois !== null ? $this->limpar($depois, $excluir) : null,
            'ip' => $this->ipAtual(),
            'criado_em' => now(),
        ]);
    }

    /**
     * @param  array<string,mixed>  $dados
     * @param  list<string>  $excluir
     * @return array<string,mixed>
     */
    private function limpar(array $dados, array $excluir): array
    {
        return array_diff_key($dados, array_flip($excluir));
    }

    private function ipAtual(): ?string
    {
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null; // CLI/ETL/jobs — sem request.
        }
    }
}
