<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Cidade;
use App\Bairro;
use App\Telefonetipo;
use Illuminate\Http\Request;

/**
 * S2 — lookups para selects do SPA (cidade → bairro, etc.). Listas enxutas
 * {id,label} com busca por texto, para selects assíncronos no React.
 */
class LookupController extends Controller
{
    /** GET /api/admin/lookups/cidades?q= */
    public function cidades(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        return response()->json(
            Cidade::query()
                ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
                ->orderBy('descricao')
                ->limit(30)
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->descricao . ($c->uf ? " / {$c->uf}" : ''), 'uf' => $c->uf])
        );
    }

    /** GET /api/admin/lookups/bairros?cidade_id=&q= */
    public function bairros(Request $request)
    {
        $cidadeId = (int) $request->query('cidade_id', 0);
        $q = trim((string) $request->query('q', ''));

        if (! $cidadeId) {
            return response()->json([]);
        }

        return response()->json(
            Bairro::query()
                ->where('cidade_id', $cidadeId)
                ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
                ->orderBy('descricao')
                ->limit(50)
                ->get()
                ->map(fn ($b) => ['id' => $b->id, 'label' => $b->descricao])
        );
    }

    /** GET /api/admin/lookups/telefone-tipos */
    public function telefoneTipos(Request $request)
    {
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;

        return response()->json(
            Telefonetipo::query()
                ->where('ativo', 1)
                ->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
                ->orderBy('descricao')
                ->get()
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->descricao])
        );
    }
}
