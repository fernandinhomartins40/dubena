@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
@media print{@page{size:landscape;}}
</style>
<!-- {{$count = 0}} -->
<!-- {{$first = true}} -->
<div class="fontSize14">
	@if(isset($geral) && count($geral) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;font-size:11px;">
            @foreach($geral as $frota)
                @if(isset($frota->totalkm))
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Total</th>
                        <th class="bordered destaque">{{$frota->totalkm}}</th>
                        <th class="bordered destaque">{{$frota->totalrodado}}</th>
                        <th class="bordered destaque">{{$frota->totalped}}</th>
                        <th class="bordered destaque">{{$frota->totalprod}}</th>
                        <th class="bordered destaque">{{number_format($frota->totkmentrega,2,',','.')}}</th>
                        <th class="bordered destaque">{{$frota->tempoentrega}}</th>
                        <th></th>
                    </tr>
                @elseif(isset($frota->data))
                    @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="9"><br /><hr /></td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="3" class="top-style fontSize15">Data: {{$frota->data}}</td>
                    </tr>
                    <!-- {{$first = false}} -->
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Veículo</th>
                        <th class="bordered destaque">Km Rodado</th>
                        <th class="bordered destaque">Tempo Rodado</th>
                        <th class="bordered destaque">Qtd. Pedidos</th>
                        <th class="bordered destaque">Qtd. Produtos</th>
                        <th class="bordered destaque">Km/Entrega</th>
                        <th class="bordered destaque">Tempo/Entrega</th>
                        <th></th>
                    </tr>
                @elseif(isset($frota->veiculo))
                    <tr>
                        <td></td>
                        <td class="bordered align-left">{{$frota->veiculo}}</td>
                        <td class="bordered">{{$frota->kmrodado}}</td>
                        <td class="bordered">{{$frota->temporodado}}</td>
                        <td class="bordered">{{$frota->qtdaped}}</td>
                        <td class="bordered">{{$frota->qtdaprod}}</td>
                        <td class="bordered">{{number_format($frota->kmentrega,2,',','.')}}</td>
                        <td class="bordered">{{$frota->tempopedido}}</td>
                        <td></td>
                    </tr>
                @endif
                <!-- {{$count++}} -->
                @if($count == count($geral))
                    <tr>
                        <td colspan="9"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Total Geral</th>
                        <th class="bordered destaque">{{$frota->geralkm}}</th>
                        <th class="bordered destaque">{{$frota->geralrodado}}</th>
                        <th class="bordered destaque">{{$frota->geralped}}</th>
                        <th class="bordered destaque">{{$frota->geralprod}}</th>
                        <th class="bordered destaque">{{number_format($frota->geralkmentrega,2,',','.')}}</th>
                        <th class="bordered destaque">{{$frota->geraltempoentrega}}</th>
                        <th></th>
                    </tr>
                @endif
            @endforeach
        </table>    
    @else
        <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
    @endif
</div>

@endsection