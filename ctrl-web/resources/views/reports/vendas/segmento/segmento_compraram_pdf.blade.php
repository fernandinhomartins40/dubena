@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
<!-- {{$first = true}} -->
<!-- {{$cont = 0}}  -->
@if(isset($pedidos) && count($pedidos) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($pedidos as $pedido)
            @if(isset($pedido->quanttotal))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="2">Total Cliente</th><!-- data -->
                    <th class="bordered destaque">{{$pedido->quanttotal}}</th><!-- quantidade -->
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->vendatotal)}}</th><!-- total -->
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle((double)$pedido->totaldesc)}}</th><!-- desconto -->
                    <th class="bordered destaque"></th>
                    <th></th>
                </tr>
                <tr>
                    <td colspan="8"></td>
                </tr>
            @elseif(isset($pedido->qtdprod))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Total</th>
                    <th class="bordered destaque money">Preço Médio: {{$pedido->media}}</th>
                    <th class="bordered destaque">{{$pedido->qtdprod}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->total)}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle((double)$pedido->totaldesc)}}</th>
                    <th class="bordered destaque"></th>
                    <th></th>
                </tr>
                <tr>
                    <td colspan="8"></td>
                </tr>
            @elseif(isset($pedido->segmento_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="8" class="top-style fontSize15 nobord">Segmento: {{$pedido->segmento}}</td>
                </tr>
                <!-- {{$first = false}} -->
            @elseif(isset($pedido->cliente_id))
                <tr style="padding-top: 10px">
                    <td colspan="8" style="padding:8px 0px" class="nobord">&nbsp;&nbsp;&nbsp;&nbsp;Cliente: {{$pedido->cliente}} - {{$pedido->endereco}}</td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque">Data</th>
                    <th class="bordered destaque">Produto</th>
                    <th class="bordered destaque">Qtd.</th>
                    <th class="bordered destaque money">Valor</th>
                    <th class="bordered destaque money">Desconto</th>
                    <th class="bordered destaque">Setor</th>
                    <th></th>
                </tr>
            @elseif(isset($pedido->data))
                <tr>
                    <td></td>
                    <td class="bordered">{{$pedido->data}}</td>
                    <td class="bordered">{{$pedido->produto}}</td>
                    <td class="bordered">{{$pedido->quantidade}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($pedido->valor)}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($pedido->desconto)}}</td>
                    <td class="bordered">{{$pedido->setor}}</td>
                    <td></td>
                </tr>
            @elseif(isset($pedido->segmentoproduto_id))
                <tr>
                    <td></td>
                    <td class="bordered destaque"></td>
                    <td class="bordered destaque money">{{$pedido->segmentoproduto}}</td>
                    <td class="bordered destaque">{{$pedido->segmentoqtde}}</td>
                    <td class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->segmentovalor)}}</td>
                    <td class="bordered destaque money">{{requestNumeroDecimalOracle((double)$pedido->segmentodesconto)}}</td>
                    <td class="bordered destaque"></td>
                    <td></td>
                </tr>
            @elseif(isset($pedido->segmentototal))
                <tr>
                    <td colspan="8" class="top-style fontSize15 nobord">{{$pedido->segmentototal}}</td>
                </tr>

            @endif
        @endforeach
    </table>
@else
    <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection