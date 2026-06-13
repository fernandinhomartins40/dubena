<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 20/07/2018
 * Time: 15:02
 */

namespace App\Repository;

use App\ClienteEndereco;

/**
 * Class EnderecoRepository
 * @package App\Repository
 * @method static create(array $data)
 * @method static find($enderecopadrao_id)
 */
class EnderecoRepository extends BaseRepository
{
    /**
     * EnderecoRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(ClienteEndereco::class);
    }

    public static function getByClient($id)
    {
        return static::whereClienteId($id)->whereAtivo(true)->get();
    }

    public static function getForMigration()
    {
        return static::select("uf", "cidade", "bairro", "rua", "cep")
            ->where("cidade", "Guarapuava")
            ->orderBy("created_at")
            ->get();
    }
}
