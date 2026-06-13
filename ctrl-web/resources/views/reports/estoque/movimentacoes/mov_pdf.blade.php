@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
@media print{@page{size:landscape;}}
</style>
<div class="fontSize_11">
<!-- {{$first = true}} -->
@if(isset($movimentacoes) && count($movimentacoes)>0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($movimentacoes as $movimentacao)
            @if(isset($movimentacao->setor_id))
                <tr>
                    <td colspan="3" class="top-style fontSize15 nobord">Setor: {{$movimentacao->setor}}</td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Data/Hora</th>
                    <th class="bordered destaque align-left">Movimentação</th>
                    <th class="bordered destaque">Produto</th>
                    <th class="bordered destaque">Qtde</th>
                    <th class="bordered destaque">Cód. Origem</th>
                    <th class="bordered destaque align-left">Origem</th>
                    <th class="bordered destaque align-left">Usuário</th>
                    <th class="bordered destaque">Saldo Inic.</th>
                    <th class="bordered destaque">Saldo Final</th>
                    <th></th>
                </tr>
            @elseif(isset($movimentacao->data))
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$movimentacao->data}}</td>
                    <td class="bordered align-left">{{$movimentacao->movimentacao}}</td>
                    <td class="bordered">{{$movimentacao->produto}}</td>
                    <td class="bordered">{{$movimentacao->quantidade}}</td>
                    <td class="bordered">{{$movimentacao->origem}}</td>
                    <td class="bordered align-left">{{$movimentacao->motivo}}</td>
                    <td class="bordered align-left">{{$movimentacao->user}}</td>
                    @if($movimentacao->first)
                        <td class="bordered destaque-saldos">{{$movimentacao->inicial}}</td>
                    @else
                        <td class="bordered">{{$movimentacao->inicial}}</td>
                    @endif
                    @if($movimentacao->last)
                        <td class="bordered destaque-saldos">{{$movimentacao->final}}</td>
                    @else
                        <td class="bordered">{{$movimentacao->final}}</td>
                    @endif
                    <td></td>
                </tr>
            @endif
        @endforeach
    </table>
@else
    <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
@endif
</div>
@endsection