@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @media print{@page{size:landscape;}}
</style>
<div class="fontSize11">
<!-- {{$first = true}} -->
	@if(isset($diretas) && count($diretas) > 1)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($diretas as $direta)
                @if(isset($direta->total))
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="5">Total do Setor</th>
                        <th class="bordered destaque">{{$direta->quantidadetotal}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($direta->total)}}</th>
                        <th></th>
                    </tr>
                @elseif(isset($direta->setor))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="8" class="nobord"><br /><hr /></td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="8" class="top-style fontSize15 nobord">Setor: {{$direta->setor}}</td>
                    </tr>
                    <!-- {{$first = false}} -->
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Data</th>
                        <th class="bordered destaque align-left">Cliente</th>
                        <th class="bordered destaque align-left">Endereço</th>
                        <th class="bordered destaque align-left">Telefone</th>
                        <th class="bordered destaque">Produto</th>
                        <th class="bordered destaque">Qtde.</th>
                        <th class="bordered destaque money">Valor</th>
                        <th></th>
                    </tr>
                @elseif(isset($direta->razao))
                    <tr>
                        <td></td>
                        <td class="bordered">{{$direta->data}}</td>
                        <td class="bordered align-left">{{$direta->razao}}</td>
                        <td class="bordered align-left">{{$direta->endereco}}</td>
                        <td class="bordered align-left">{{$direta->telefone}}</td>
                        <td class="bordered">{{$direta->produto}}</td>
                        <td class="bordered">{{$direta->quantidade}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($direta->valor)}}</td>
                        <td></td>
                    </tr>
                @endif
                @if($loop->last)
                    <tr>
                        <td colspan="8"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="5">Total Geral</th>
                        <th class="bordered destaque">{{$direta->quantidadetotal}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($direta->totalgeral)}}</th>
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