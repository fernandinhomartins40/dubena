<?php

namespace App\Http\Controllers;

use App\{Cupom, Pedido, Repository\PedidoRepository, Services\CarbonCustom};
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Input;
use phpDocumentor\Reflection\Types\String_;

class CouponsController extends Controller
{
    public static function getCouponIfValid($code, $client_id)
    {
        $coupons = Cupom::where('codigo', $code)->get();
        if ($coupons->isEmpty())
            return 'Cupom inválido.';

        $coupon = $coupons->first();

        $now = CarbonCustom::now();
        if ($now > $coupon->datafim)
            return 'O cupom já expirou.';

        if ($now < $coupon->datainicio || !$coupon->ativo)
            return 'O cupom inválido.';

        $hasOrdersWithForClientCoupon = PedidoRepository::hasValidOrdersForClientWithCoupon($coupon->id, $client_id);
        if ($hasOrdersWithForClientCoupon)
            return 'Você já usou esse cupom.';

        $ordersWithCouponCount = PedidoRepository::validOrdersWithCouponCount($coupon->id);

        if ($coupon->limiteuso <= $ordersWithCouponCount)
            return 'O cupom estourou o limite de uso.';

        return $coupon;
    }

    public function verify(Request $request)
    {
        try {
            $code = $request->get('codigo_cupom');

            $coupon = self::getCouponIfValid($code, $request->get('client_id'));

            if (is_string($coupon))
                return responseReject($coupon, 422);

            return responseSuccess([
                'code' => $coupon->codigo,
                'type' => $coupon->tipo,
                'value' => $coupon->valor
            ], "OK");
        } catch (Exception $ex) {
            return responseError($ex->getMessage(), 500);
        }
    }

    /**
     * @param int $clientId
     * @throws Exception
     */
    public static function available(?int $clientId)
    {
        if ($clientId == null)
            throw new Exception('O campo cliente é obrigatório.');

        $raw = "select c.codigo, c.tipo, c.valor, count(p.id) as quantidadeuso from cupons as c
                left join
                    (select ped.id, ped.cupom_id
                        from pedidos ped
                            inner join pedidosituacoes ps on ped.pedidosituacao_id = ps.id
                        where ps.cancelado = 0 and cupom_id IS NOT NULL
                    ) as p on c.id = p.cupom_id
                left join
                    (select ped.id, ped.cupom_id
                     from pedidos ped
                              inner join pedidosituacoes ps on ped.pedidosituacao_id = ps.id
                     where ps.cancelado = 0 and cupom_id IS NOT NULL and cliente_id = :clienteId
                    ) as pc on c.id = pc.cupom_id
                 where
                       now() between c.datainicio and c.datafim
                   and c.ativo = 1
                group by c.codigo, c.tipo, c.valor, c.limiteuso
                having count(p.id) < c.limiteuso and count(pc.id) = 0;
            ";

        $coupons = DB::select(DB::raw($raw), array(
            ':clienteId' =>  $clientId,
        ));

        if (isset($coupons[0]))
            return $coupons[0];

        return null;
    }

    public function get()
    {
        $client_id = request()->get("cliente_id");

        try {
            $coupon = $this->available($client_id);

            return responseSuccess($coupon);
        } catch (\Exception $ex) {
            return responseError($ex->getMessage());
        }
    }
}
