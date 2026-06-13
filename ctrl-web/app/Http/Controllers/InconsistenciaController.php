<?php

namespace App\Http\Controllers;

use App\Bairro;
use DB;
use App\Rua;
use Session;
use App\Estado;

class InconsistenciaController extends Controller
{
    public function index()
    {
        $empresa = Session::get('empresa_padrao');
        $uf_empresa = $empresa->uf;
        $cidade_empresa = $empresa->cidade_id;
        $estados = Estado::pluck('uf', 'uf')->prepend('Selecione', '');

        return view("endereco.inconsistencia.index", compact("uf_empresa", "cidade_empresa", "estados"));
    }

    public function getInconsistencias()
    {
        $cidade_id = request()->get("cidade_id");
        $tipo = request()->get("tipo");

        $grupo_id = Session::get("empresa_padrao")->grupo_id;

        $query = "";
        $data = null;
        switch ($tipo) {
            case "1":
                $query = $this->getQueryInconRua();
                $data = DB::select($query, ["grupo" => $grupo_id, "cidade" => $cidade_id]);
                break;
            case "2":
                $query = $this->getQueryInconBairro();
                $data = DB::select($query, ["grupo" => $grupo_id, "cidade" => $cidade_id]);
                break;
        }

        return response()->json([
            "data" => $data,
        ]);
    }

    public function ignorarRua()
    {
        $rua_id = request()->get("rua_id");
        $ruaignore_id = request()->get("ruaignore_id");

        $rua = Rua::find($rua_id);

        DB::beginTransaction();
        try {
            $rua->ignorados()->attach($ruaignore_id, [
                "ignored_type" => Rua::class
            ]);

            DB::commit();

            return response()->json(["msg" => "sucesso"], 200);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(["msg" => $ex->getMessage()], 400);
        }
    }

    public function ignorarBairro()
    {
        $bairro_id = request()->get("bairro_id");
        $bairroignore_id = request()->get("bairroignore_id");

        $bairro = Bairro::find($bairro_id);

        DB::beginTransaction();
        try {
            $bairro->ignorados()->attach($bairroignore_id, [
                "ignored_type" => Bairro::class
            ]);

            DB::commit();

            return response()->json(["msg" => "sucesso"], 200);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(["msg" => $ex->getMessage()], 400);
        }
    }

    public function getRegistros()
    {
        $data_id = request()->get("data_id");
        $datacomp_id = request()->get("datacomp_id");
        $tipo = request()->get("tipo");

        $query = "";
        $data = null;
        $dataComp = null;

        switch ($tipo) {
            case "1":
                $query = $this->queryRuasUtilizadas();
                $data = collect(DB::select($query, ["rua" => $data_id]));
                $dataComp = collect(DB::select($query, ["rua" => $datacomp_id]));
                break;
            case "2":
                $query = $this->queryBairrosUtilizadas();
                $data = collect(DB::select($query, ["bairro" => $data_id]));
                $dataComp = collect(DB::select($query, ["bairro" => $datacomp_id]));
                break;
        }

        $callback = function ($it) {
            if (empty($it->rota)) return $it;

            $it->rota_url = route($it->rota, ["id" => $it->id]);

            return $it;
        };

        $data->transform($callback);

        $dataComp->transform($callback);

        return response()->json([
            "data" => $data,
            "data_comp" => $dataComp,
        ]);
    }

    public function getQueryInconRua()
    {
        $query = "WITH norm_ruas AS (
                SELECT id, REGEXP_REPLACE(TRANSLATE(
                    UPPER(descricao),
                'ÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇáàâãäéèêëíìîïóòôõöúùûüçÑñ',
                'AAAAAEEEEIIIIOOOOOUUUUCaaaaaeeeeiiiiooooouuuucNn'
                ),'[^A-Z0-9 ]', '') AS norm_desc, descricao
                FROM ruas
                WHERE cidade_id = :cidade
                AND grupo_id = :grupo
                AND ativo = 1
            )
            SELECT r1.id AS id, r1.DESCRICAO AS descricao, r2.id AS id_comp, r2.DESCRICAO AS descricao_comp
            FROM norm_ruas r1
            JOIN norm_ruas r2 ON r1.norm_desc < r2.norm_desc
            WHERE UTL_MATCH.JARO_WINKLER_SIMILARITY(r1.norm_desc, r2.norm_desc) >= 96

            MINUS

            SELECT r1.id, r1.descricao, r2.id AS id_comp, r2.descricao AS descricao_comp
            FROM inconsistencia_ignorada ign
            INNER JOIN ruas r1 ON ign.model_id = r1.id
            INNER JOIN ruas r2 ON ign.ignored_id = r2.id
            WHERE r1.grupo_id = :grupo
            AND r1.cidade_id = :cidade
            AND ign.model_type = 'App\Rua'
            AND ign.ignored_type = 'App\Rua'
            ORDER BY id";

        return $query;
    }

    public function getQueryInconBairro()
    {
        $query = "WITH norm_bairro AS (
                SELECT id, REGEXP_REPLACE(TRANSLATE(
                    UPPER(descricao),
                'ÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇáàâãäéèêëíìîïóòôõöúùûüçÑñ',
                'AAAAAEEEEIIIIOOOOOUUUUCaaaaaeeeeiiiiooooouuuucNn'
                ),'[^A-Z0-9 ]', '') AS norm_desc, descricao
                FROM bairros
                WHERE cidade_id = :cidade
                AND grupo_id = :grupo
            )
            SELECT b1.id AS id, b1.DESCRICAO AS descricao, b2.id AS id_comp, b2.DESCRICAO AS descricao_comp
            FROM norm_bairro b1
            JOIN norm_bairro b2 ON b1.norm_desc < b2.norm_desc
            WHERE UTL_MATCH.JARO_WINKLER_SIMILARITY(b1.norm_desc, b2.norm_desc) >= 92

            MINUS

            SELECT b1.id, b1.descricao, b2.id AS id_comp, b2.descricao AS descricao_comp
            FROM inconsistencia_ignorada ign
            INNER JOIN bairros b1 ON ign.model_id = b1.id
            INNER JOIN bairros b2 ON ign.ignored_id = b2.id
            WHERE b1.grupo_id = :grupo
            AND b1.cidade_id = :cidade
            AND ign.model_type = 'App\Bairro'
            AND ign.ignored_type = 'App\Bairro'
            ORDER BY id";

        return $query;
    }

    private function queryRuasUtilizadas()
    {
        $query = "SELECT id, descricao, 'agencia.edit' AS rota, 1 AS qtd, 'Agência' AS tipo
            FROM agencias
            WHERE rua_id = :rua

            UNION ALL

            SELECT id, nome AS descricao, 'cliente.edit' AS rota, 1 AS qtd, 'Cliente' AS tipo
            FROM clientes
            WHERE rua_id = :rua

            UNION ALL

            SELECT id, nome AS descricao, 'colaborador.edit' AS rota, 1 AS qtd, 'Colaborador' AS tipo
            FROM colaboradors
            WHERE rua_id = :rua

            UNION ALL

            SELECT id, 'Contador - ' || razao_social AS descricao, 'empresa.edit' AS rota, 1 AS qtd, 'Contador' AS tipo
            FROM empresas
            WHERE contrua_id = :rua

            UNION ALL

            SELECT id, 'Empresa - ' || razao_social AS descricao, 'empresa.edit' AS rota, 1 AS qtd, 'Empresa' AS tipo
            FROM empresas
            WHERE rua_id = :rua

            UNION ALL

            SELECT null AS id, 'Pedidos' AS descricao, '' AS rota, count(id) AS qtd, 'Pedido' AS tipo
            FROM pedidos
            WHERE entregarua_id = :rua

            UNION ALL

            SELECT null AS id, 'Promover Vendas' AS descricao, '' AS rota, count(id) AS qtd, 'Promover Vendas' AS tipo
            FROM promotorvendas
            WHERE rua_id = :rua

            UNION ALL

            SELECT id, descricao, 'setor.edit' AS rota, 1 AS qtd, 'Setor' AS tipo
            FROM setors
            WHERE rua_id = :rua
            ORDER BY tipo, descricao";

        return $query;
    }

    private function queryBairrosUtilizadas()
    {
        $query = "SELECT id, descricao, 'agencia.edit' AS rota, 1 AS qtd, 'Agência' AS tipo
            FROM agencias
            WHERE bairro_id = :bairro

            UNION ALL

            SELECT id, nome AS descricao, 'cliente.edit' AS rota, 1 AS qtd, 'Cliente' AS tipo
            FROM clientes
            WHERE bairro_id = :bairro

            UNION ALL

            SELECT id, nome AS descricao, 'colaborador.edit' AS rota, 1 AS qtd, 'Colaborador' AS tipo
            FROM colaboradors
            WHERE bairro_id = :bairro

            UNION ALL

            SELECT id, 'Contador - ' || razao_social AS descricao, 'empresa.edit' AS rota, 1 AS qtd, 'Contador' AS tipo
            FROM empresas
            WHERE contbairro_id = :bairro

            UNION ALL

            SELECT id, 'Empresa - ' || razao_social AS descricao, 'empresa.edit' AS rota, 1 AS qtd, 'Empresa' AS tipo
            FROM empresas
            WHERE bairro_id = :bairro

            UNION ALL

            SELECT null AS id, 'Pedidos' AS descricao, '' AS rota, count(id) AS qtd, 'Pedido' AS tipo
            FROM pedidos
            WHERE entregabairro_id = :bairro

            UNION ALL

            SELECT null AS id, 'Promover Vendas' AS descricao, '' AS rota, count(id) AS qtd, 'Promover Vendas' AS tipo
            FROM promotorvendas
            WHERE bairro_id = :bairro

            UNION ALL

            SELECT id, descricao, 'setor.edit' AS rota, 1 AS qtd, 'Setor' AS tipo
            FROM setors
            WHERE bairro_id = :bairro
            ORDER BY tipo, descricao";

        return $query;
    }
}
