<?php

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 20/07/2018
 * Time: 15:02
 */

namespace App\Api\Repository;

use App\Api\Models\ClienteImportacao;

/**
 * Class ClienteRepository
 * @package App\Repository
 * @method static updateOrCreate($data)
 * @method static find(int $id)
 * @mixin ClienteImportacao
 */
class ClienteRepository extends BaseRepository
{
    /**
     * ClienteRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(ClienteImportacao::class);
    }

    /**
     * @param null $user_id
     * @return mixed
     */
    public static function getToLink($user_id = null)
    {
        return static::from("clienteimportacoes as c")
            ->selectRaw("nome, c.id, sexo, datanascimento, telefone, " .
                "end.numero, end.complemento, end.rua, end.cep, end.latitude, end.longitude," .
                "end.bairro, end.uf, end.cidade, end.pontoreferencia, end.id as endereco_id")
            ->join("clientetelefones as tel", "tel.cliente_id", "c.id")
            ->leftJoin("clienteenderecos as end", "c.enderecopadrao_id", "end.id")
            ->whereUserId($user_id)
            ->whereRaw("c.ativo = 1")->orderBy("nome")->get();
    }

    /**
     * @param $client_id
     * @param $address_id
     * @return mixed
     * @throws \Exception
     */
    protected static function updateFavoriteAddress($client_id, $address_id)
    {
        $client = static::findOrFail($client_id);
        $client->update(["enderecopadrao_id" => $address_id]);
        return $client;
    }

    /**
     * @param $address_id
     * @return mixed
     * @throws \Exception
     */
    public static function getByEndereco($address_id)
    {
        return static::whereRaw("enderecopadrao_id = " . $address_id);
    }

    /**
     * @param $ids
     * @return mixed
     */
    public static function toNofify($ids)
    {
        return static::whereIn("id", $ids)->select("pushregistration_id as registration_id")
            ->get()->pluck("registration_id")->toArray();
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public static function getErpId($id)
    {
        return static::findOrFail($id)->erp_id;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public static function withPhone($id)
    {
        return static::whereRaw("cliente_id = " . $id)
            ->leftJoin("clientetelefones as tel", "tel.cliente_id", "clienteimportacoes.id")
            ->selectRaw("clienteimportacoes.*, cliente_id, telefone")
            ->get();
    }

    public static function getForMigration()
    {
        return static::from("clienteimportacoes as cli")
            ->rightJoin("pedidos as pe", "pe.cliente_id", "cli.id")
            ->join("clienteenderecos as en", "en.id", "cli.enderecopadrao_id")
            ->join("clientetelefones as te", "te.cliente_id", "cli.id")
            ->whereRaw("cli.nome <> 'Cliente inexistente' and en.cidade = 'Guarapuava'")
            ->whereIn("cli.id", [959, 73, 1071])
            ->select(
                "cli.id",
                "cli.nome",
                "cli.enderecopadrao_id",
                "cli.user_id",
                "cli.ativo",
                "cli.datanascimento",
                "cli.sexo",
                "te.telefone",
                "en.uf",
                "en.cidade",
                "en.bairro",
                "en.rua",
                "en.cep",
                "en.complemento",
                "en.pontoreferencia",
                "en.numero",
                'en.longitude',
                "en.latitude",
                "cli.email"
            )
            ->groupBy(
                "cli.id",
                "cli.nome",
                "cli.enderecopadrao_id",
                "cli.user_id",
                "cli.ativo",
                "cli.datanascimento",
                "cli.sexo",
                "te.telefone",
                "en.uf",
                "en.cidade",
                "en.bairro",
                "en.rua",
                "en.cep",
                "en.complemento",
                "en.pontoreferencia",
                "en.numero",
                'en.longitude',
                "en.latitude",
                "cli.email"
            )
            ->orderBy("cli.id")
            ->get();
    }

    public static function getAtivosNotificar()
    {
        return static::whereRaw("pushregistration_id IS NOT NULL AND ativo = 1 AND nome not like 'Cliente inexistente'")
            ->select("pushregistration_id as registration_id", "id")
            ->get();
    }

    public static function getAtivosNotificarCupom()
    {
        return static::whereRaw("pushregistration_id IS NOT NULL AND ativo = 1 AND nome not like 'Cliente inexistente' "
            . "AND appbuildnumber IS NOT NULL")
            ->select("pushregistration_id as registration_id", "id")
            ->get();
    }

    public static function getNotificarById($ids)
    {
        return static::whereRaw("pushregistration_id IS NOT NULL AND ativo = 1 AND nome not like 'Cliente inexistente'")
            ->whereIn("id", $ids)
            ->select("pushregistration_id as registration_id", "id")
            ->get();
    }

    public static function findByCpf($cpf)
    {
        return static::where("cpf", $cpf)->first();
    }
}

