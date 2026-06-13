@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @page{margin-bottom: 15px !important;}
    @media print {
        @page{size: landscape;}
    }
</style>
<div class="fontSize14">
    <!-- {{$first = true}} -->
    <!-- {{$setorAnterior = -1}} -->
    <!-- {{$segmentoAnterior = -1}} -->
    <!-- {{$produtoAnterior = -1}} -->
    <!-- {{$totalFiltroPrincipal = 0}} -->
    @if(isset($clientes) && count($clientes) > 0)
    <table style="margin-top:8px; margin-left: -5px; min-width:100%; font-size: 9px">
        @foreach($clientes as $cliente) 
        <!-- {{$tipo0 = $tipo == '0' && $produtoAnterior != $cliente->produto_id}}
        {{$tipo1 = $tipo == '1' && $segmentoAnterior != $cliente->segmento_id}}
        {{$tipo2 = $tipo == '2' && $setorAnterior != $cliente->setor_id}} -->
        @if(($tipo0) || ($tipo1) || ($tipo2))
            @if(!$loop->first)
                <tr>
                    <th></th>	
                    @if($tipo == '0')
                    <th class="bordered destaque align-left" colspan="6">Total Produto</th>
                    <th class="bordered destaque">{{$clientes->where('produto_id', $produtoAnterior)->sum('quantidade')}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($clientes->where('produto_id', $produtoAnterior)->sum('precovendatotal'))}}</th>
                    @elseif($tipo == '1')
                    <th class="bordered destaque align-left" colspan="6">Total Segmento</th>
                    <th class="bordered destaque">{{$clientes->where('segmento_id', $segmentoAnterior)->sum('quantidade')}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($clientes->where('segmento_id', $segmentoAnterior)->sum('precovendatotal'))}}</th>
                    @else
                    <th class="bordered destaque align-left" colspan="6">Total Setor</th>
                    <th class="bordered destaque">{{$clientes->where('setor_id', $setorAnterior)->sum('quantidade')}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($clientes->where('setor_id', $setorAnterior)->sum('precovendatotal'))}}</th>
                    @endif
                    <th></th>
                </tr>
            @endif
                <tr><td></td></tr>
                <tr>
                    @if($tipo == '0')
                        <td class="negrito"  colspan="8">{{$cliente->produto}}</td>
                    @elseif($tipo == '1')
                        <td class="negrito"  colspan="8">{{$cliente->segmento}}</td>
                    @else
                        <td class="negrito"  colspan="8">{{$cliente->setor == 'ZZZZZZZ-SEMSETOR' ? "Sem Setor" : $cliente->setor}}</td>
                    @endif
                </tr>
                <tr>
                    <th></th>	
                    <th class="bordered destaque">Cód.</th>
                    <th class="bordered destaque align-left">Cliente</th>
                    @if($tipo == '0')
                        <th class="bordered destaque align-left">Segmento</th>
                        <th class="bordered destaque align-left">Setor</th>
                    @elseif($tipo == '1')
                        <th class="bordered destaque align-left">Produto</th>
                        <th class="bordered destaque align-left">Setor</th>
                    @else
                        <th class="bordered destaque align-left">Segmento</th>
                        <th class="bordered destaque align-left">Produto</th>
                    @endif
                    <th class="bordered destaque">Endereço</th>
                    <th class="bordered destaque">Últ. Compra</th>
                    <th class="bordered destaque">Qtde</th>
                    <th class="bordered destaque">Valor</th>
                    <th></th>	
                </tr>
        @endif
            <tr>
                <td></td>
                    <td class="bordered">{{$cliente->cliente_id}}</td>	
                    <td class="bordered align-left">{{strlen($cliente->nome) <= 42 ? $cliente->nome : substr($cliente->nome, 0, 45) . '...'}}</td>
                    @if($tipo == '0')
                        <td class="bordered align-left">{{$cliente->segmento}}</td>
                        <td class="bordered align-left">{{$cliente->setor == 'ZZZZZZZ-SEMSETOR' ? "Sem Setor" : $cliente->setor}}</td>
                    @elseif($tipo == '1')
                        <td class="bordered align-left">{{$cliente->produto}}</td>
                        <td class="bordered align-left">{{$cliente->setor == 'ZZZZZZZ-SEMSETOR' ? "Sem Setor" : $cliente->setor}}</td>
                    @else
                        <td class="bordered align-left">{{$cliente->segmento}}</td>
                        <td class="bordered align-left">{{$cliente->produto}}</td>
                    @endif
                    <td class="bordered align-left">{{strlen($cliente->endereco) <= 58 ? $cliente->endereco : substr($cliente->endereco, 0, 55) . '...'}}</td>
                    <td class="bordered">{{requestDataOracle($cliente->datahoraprevisaoentrega, false)}}</td>
                    <td class="bordered">{{$cliente->quantidade}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($cliente->precovendatotal)}}</td>	
                <td></td>
            </tr>
        @if($loop->last)
            @if($tipo == '0')
                <!-- {{$produtoAnterior = $cliente->produto_id}} -->
            @elseif($tipo == '1')
                <!-- {{$segmentoAnterior = $cliente->segmento_id}} -->
            @else
                <!-- {{$setorAnterior = $cliente->setor_id}} -->
            @endif
            <tr>
                <th></th>	
                @if($tipo == '0')
                <th class="bordered destaque align-left" colspan="6">Total Produto:</th>
                <th class="bordered destaque">{{$clientes->where('produto_id', $produtoAnterior)->sum('quantidade')}}</th>
                <th class="bordered destaque money">{{requestNumeroDecimalOracle($clientes->where('produto_id', $produtoAnterior)->sum('precovendatotal'))}}</th>
                @elseif($tipo == '1')
                <th class="bordered destaque align-left" colspan="6">Total Segmento:</th>
                <th class="bordered destaque">{{$clientes->where('segmento_id', $segmentoAnterior)->sum('quantidade')}}</th>
                <th class="bordered destaque money">{{requestNumeroDecimalOracle($clientes->where('segmento_id', $segmentoAnterior)->sum('precovendatotal'))}}</th>
                @else
                <th class="bordered destaque align-left" colspan="6">Total Setor:</th>
                <th class="bordered destaque">{{$clientes->where('setor_id', $setorAnterior)->sum('quantidade')}}</th>
                <th class="bordered destaque money">{{requestNumeroDecimalOracle($clientes->where('setor_id', $setorAnterior)->sum('precovendatotal'))}}</th>
                @endif
                <th></th>
            </tr>
            <tr><td></td></tr>
            <tr>
                <td></td>
                <td class='align-left destaque negrito bordered' colspan="6">Total Geral:</td>
                <td class='destaque negrito bordered'>{{$clientes->sum('quantidade')}}</td>
                <td class='money destaque negrito bordered'>{{requestNumeroDecimalOracle($clientes->sum('precovendatotal'))}}</td>
                <td></td>
            </tr>
        @endif
        @if($tipo == '0')
            <!-- {{$produtoAnterior = $cliente->produto_id}} -->
        @elseif($tipo == '1')
            <!-- {{$segmentoAnterior = $cliente->segmento_id}} -->
        @else
            <!-- {{$setorAnterior = $cliente->setor_id}} -->
        @endif
        @endforeach
    </table>
    @else
    <br />
    <p style="text-align:center" class="negrito">Nenhum resultado encontrado para estes filtros!</p>
    @endif
</div>

@endsection