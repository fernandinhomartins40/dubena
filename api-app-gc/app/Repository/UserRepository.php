<?php

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:32
 */

namespace App\Repository;


use App\Services\CarbonCustom;
use App\User;
use App\Services\CarbonCustom as Carbon;
use Exception;

class UserRepository extends BaseRepository
{

    /**
     * UserRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(User::class);
    }

    /**
     * @param $service_user
     * @return mixed
     * @throws Exception
     */
    public static function getLinked($service_user)
    {
        $user = static::find($service_user);
        if (! $user) {
            throw new Exception("Ops! Parece que seu usuário não está vinculado à API!");
        }
        return $user;
    }

    /**
     * @param $service_user
     * @return mixed
     * @throws Exception
     */
    public static function getByServiceUser($service_user, $id)
    {
        $user = (new static)::where("id", "<>", $id)
            ->whereServiceuserId($service_user)->whereRaw("serviceuser_id is not null")->first();
        return $user;
    }

    /**
     * @param $uf
     * @return \Illuminate\Support\Collection
     * @throws Exception
     */
    public static function getAllowedToOrder($uf)
    {
        try {

            $tz = getTimezone($uf);

            $date = Carbon::now($tz);
        } catch (Exception $e) {
            throw new Exception("A dada informada está num formato incorreto");
        }

        $rawJoin = \DB::raw("(SELECT COUNT(*) as count, p.user_id, AVG(av.rating) as avg " .
            "FROM pedidoavaliacoes AS av " .
            "INNER JOIN pedidos p ON p.id = av.pedido_id " .
            "GROUP BY user_id) AS av");

        $users = static::join("userpolylines as pol", "pol.user_id", "users.id")
            ->leftJoin("feriados as f", function ($join) use ($date) {
                $join->whereRaw("(f.data IS NULL OR (f.data = '" . $date->toDateString() . "') AND f.ativo = 1)")
                    ->on("f.user_id", "=", "users.id");
            })
            ->leftJoin($rawJoin, "av.user_id", "users.id");

        $raw = static::mountRaw($date);

        return $users->whereRaw($raw["where"])->selectRaw($raw["select"])->get();
    }

    /**
     * @param Carbon $date
     * @return array
     */
    public static function mountRaw(CarbonCustom $date)
    {
        $hours = $date->format("H:i:s");

        $selectRaw = "pol.latitude, pol.longitude, users.id, fantasia, enderecocompleto, permiteagendamento, " .
            ":typehoraabertura as abertura, :typehorafechamento as fechamento, avaliacao, '' as thumbnail, telefone, " .
            "domingohoraabertura, domingohorafechamento, users.latitude as rev_lat, users.longitude as rev_long, " .
            "av.count as totalavaliacoes, av.avg as avaliacao, delivery_time_start, delivery_time_end, valorfretegp, gaspovoativado";
        $cast = "CAST('" . $hours . "' AS time)";

        $holidayDateRaw = "'" . $date->toDateString() . "' = f.data";

        $holidayRaw = "(" . $holidayDateRaw;
        $holidayRaw .= " AND " . "feriadohoraabertura <= " . $cast;
        $holidayRaw .= " AND " . "feriadohorafechamento >= " . $cast . ")";

        $whereRaw = "(" . $holidayRaw . " OR f.data IS NULL) AND users.ativo = 1 and users.admin = 0 ";

        $replace = "";
        if ($date->isWeekday()) {
            $replace = "semana";
        } elseif ($date->isSunday()) {
            $replace = "domingo";
        } elseif ($date->isSaturday()) {
            $replace = "sabado";
        }

        if ($replace) {
            $daysRaw = " AND ((" . $holidayDateRaw . ") OR (f.data IS NULL AND :typehoraabertura <= " . $cast .
                " AND :typehorafechamento >= " . $cast . "))";
            $whereRaw .= str_replace(":type", $replace, $daysRaw);
            $selectRaw = str_replace(":type", $replace, $selectRaw);
        }

        return [
            "where"     => $whereRaw,
            "select"    => $selectRaw
        ];
    }
}
