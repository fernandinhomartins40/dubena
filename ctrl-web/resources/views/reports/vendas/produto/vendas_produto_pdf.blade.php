@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize12">
<!-- {{$first = true}} -->
@if(isset($clientes) && count($clientes))
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($clientes as $cliente)
            @if(isset($cliente->totalsegmento))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="5">Total do Segmento</th>
                    <th class="bordered destaque">{{$cliente->quantidadetotal}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($cliente->totalsegmento)}}</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->totalproduto))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="5">Total do Produto</th>
                    <th class="bordered destaque">{{$cliente->quantidadetotal}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($cliente->totalproduto)}}</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->produto_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="8" class="top-style fontSize15 nobord">Produto: {{$cliente->produto}}</td>
                </tr>
            @elseif(isset($cliente->segmento_id))
                <tr>
                    <td colspan="8" class="top-style fontSize15 nobord">Segmento: {{$cliente->segmento}}</td>
                </tr>
                <!-- {{$first = false}} -->
                <tr>
                    <th></th>
                    <th class="bordered destaque">Cód.</th>
                    <th class="bordered destaque align-left">Condição</th>
                    <th class="bordered destaque">Data</th>
                    <th class="bordered destaque align-left">Produto</th>
                    <th class="bordered destaque align-left">Segmento</th>
                    <th class="bordered destaque">Qtde.</th>
                    <th class="bordered destaque money">Valor</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->produto))
                <tr>
                    <td></td>
                    <td class="bordered">{{$cliente->pedido_id}}</td>
                    <td class="bordered align-left">{{$cliente->condicao}}</td>
                    <td class="bordered">{{$cliente->data}}</td>
                    <td class="bordered align-left">{{$cliente->produto}}</td>
                    <td class="bordered align-left">{{$cliente->segmento}}</td>
                    <td class="bordered">{{$cliente->quantidade}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($cliente->valor)}}</td>
                    <td></td>
                </tr>                
            @endif
            @if ($loop->last)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="5">Total Geral</th>
                    <th class="bordered destaque">{{$cliente->quantidade}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($cliente->totalgeral)}}</th>
                    <th></th>
                </tr>
            @endif
        @endforeach
    </table>
@else
    <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection