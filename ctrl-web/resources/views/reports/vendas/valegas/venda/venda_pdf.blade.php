@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
@media print{@page{size:landscape;}}
</style>
<div class="fontSize12">
<!-- {{$first = true}} -->
@if(isset($clientes) && count($clientes) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($clientes as $cliente)
            @if(isset($cliente->quantidadetotal))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="3">Total</th>
                    <th class="bordered destaque">{{$cliente->quantidadetotal}}</th>
                    <th class="bordered destaque"></th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($cliente->total)}}</th>
                    <th class="bordered destaque"></th>
                    <th class="bordered destaque align-left">Média: {{requestNumeroDecimalOracle($cliente->media)}}</th>
                    <th class="bordered destaque"></th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->cliente_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="11" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td></td>
                    <td colspan="9" class="top-style fontSize15 nobord">{{$cliente->cliente}}</td>
                </tr>
                <!-- {{$first = false}} -->
                <tr>
                    <th></th>
                    <th class="bordered destaque">Cód.</th>
                    <th class="bordered destaque">Data</th>
                    <th class="bordered destaque">Produto</th>
                    <th class="bordered destaque">Qtd.</th>
                    <th class="bordered destaque money">Únitario</th>
                    <th class="bordered destaque money">Total</th>
                    <th class="bordered destaque align-left">Forma Pgto.</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th class="bordered destaque align-left">Telefone</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->id))
                <tr>
                    <td></td>
                    <td class="bordered">{{$cliente->venda_id}}</td>
                    <td class="bordered">{{$cliente->datavenda}}</td>
                    <td class="bordered">{{$cliente->produto}}</td>
                    <td class="bordered">{{$cliente->quantidade}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($cliente->valorunitario)}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($cliente->valortotal)}}</td>
                    <td class="bordered align-left fontSize12">{{$cliente->condicao}}</td>
                    <td class="bordered align-left fontSize12">{{$cliente->endereco}}</td>
                    <td class="bordered align-left fontSize12">{{$cliente->telefone}}</td>
                    <td></td>
                </tr>
            @endif
            @if ($loop->last)
                <tr>
                    <td colspan="12"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="3">Total Geral</th>
                    <th class="bordered destaque">{{$cliente->quantidade}}</th>
                    <th class="bordered destaque"></th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($cliente->valor)}}</th>
                    <th class="bordered destaque"></th>
                    <th class="bordered destaque align-left">Média: {{requestNumeroDecimalOracle($cliente->media)}}</th>
                    <th class="bordered destaque"></th>
                    <th></th>
                </tr>
            @endif
        @endforeach
    </table>
@else
    <p style="text-align:center;font-size:13px;"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection