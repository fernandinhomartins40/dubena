@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize14">
<!-- {{$first = true}} -->
@if(isset($pedidos) && count($pedidos) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($pedidos as $pedido)
            @if(isset($pedido->totalcond))
                <tr>
                    <th class="nobord"></th>
                    <th class="bordered destaque align-left" colspan="3">&nbsp;&nbsp;&nbsp;Total da Condição de Pagamento</th>
                    <th class="bordered destaque">{{$pedido->quantidadecond}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->totalcond)}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->condtotaldesc)}}</th>
                    <th class="nobord"></th>
                </tr>
            @elseif(isset($pedido->totalcol))
                <tr>
                    <td colspan="8"></td>
                </tr>
                <tr>
                    <th class="nobord"></th>
                    <th class="bordered destaque align-left" colspan="3">&nbsp;&nbsp;&nbsp;Total do Colaborador</th>
                    <th class="bordered destaque">{{$pedido->qtdcol}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->totalcol)}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->coltotaldesc)}}</th>
                    <th class="nobord"></th>
                </tr>
            @elseif(isset($pedido->colaborador))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="8" class="top-style fontSize15 nobord">Colaborador: {{$pedido->colaborador}}</td>
                </tr>
                <!-- {{$first = false}} -->
            @elseif(isset($pedido->condicao))
                <tr>
                    <td colspan="8" style="padding:8px 0px" class="nobord">&nbsp;&nbsp;&nbsp;&nbsp;Condição de Pagamento: {{$pedido->condicao}}</td>
                </tr>
                <tr>
                    <th class="nobord"></th>
                    <th class="bordered destaque">Cód. Pedido</th>
                    <th class="bordered destaque">Data Pedido</th>
                    <th class="bordered destaque">Produto</th>
                    <th class="bordered destaque">Quantidade</th>
                    <th class="bordered destaque money">Total Venda</th>
                    <th class="bordered destaque money">Desconto</th>
                    <th class="nobord"></th>
                </tr>
            @elseif(isset($pedido->produto))
                <tr>
                    <td class="nobord"></td>
                    <td class="bordered">{{$pedido->pedido_id}}</td>
                    <td class="bordered">{{$pedido->data}}</td>
                    <td class="bordered">{{$pedido->produto}}</td>
                    <td class="bordered">{{$pedido->quantidade}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($pedido->precovenda)}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($pedido->desconto)}}</td>
                    <td class="nobord"></td>
                </tr>
            @endif
            @if($loop->last)
                <tr>
                    <td colspan="8"></td>
                </tr>
                <tr>
                   <th class="nobord"></th>
                   <th class="bordered destaque align-left" colspan="3">&nbsp;&nbsp;&nbsp;Total Geral</th>
                   <th class="bordered destaque">{{$pedido->totalquantidade}}</th>
                   <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->totalgeral)}}</th>
                   <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->totaldesc)}}</th>
                   <th class="nobord"></th>
                </tr>
            @endif
        @endforeach
    </table>
@else
    <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>

@endsection