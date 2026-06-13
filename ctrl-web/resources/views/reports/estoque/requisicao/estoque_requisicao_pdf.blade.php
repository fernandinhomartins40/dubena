@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
@media print{@page{size:landscape;}}
</style>
<div class="fontSize_12">
<!-- {{$first = true}} -->
<!-- {{$count = 0}} -->
    @if(isset($requisicoes) && count($requisicoes)>0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($requisicoes as $requisicao)
            @if(isset($requisicao->totalsetor))
                <tr>
                    <th class="nobord"></th>
                    <th class="bordered destaque align-left" colspan="3">Total</th>
                    <th class="bordered destaque">{{$requisicao->totalsetor}}</th>
                    <th class="bordered destaque money">{{$requisicao->custototal}}</th>
                    <th class="bordered destaque" colspan="3"></th>
                    <th class="nobord"></th>
                </tr>
            @elseif(isset($requisicao->setor))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="10" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="3" class="top-style fontSize15 nobord">Setor: {{$requisicao->setor}}</td>
                </tr>
                <!-- {{$first = false}} -->
            @elseif(isset($requisicao->produto_id))
                <tr>
                    <td colspan="10" style="padding:8px 0px" class="nobord">&nbsp;&nbsp;&nbsp;&nbsp;Produto: {{$requisicao->produto}}</td>
                </tr>
                <tr>
                    <th class="nobord"></th>
                    <th class="bordered destaque">Cód. Requisição</th>
                    <th class="bordered destaque">Data e Hora</th>
                    <th class="bordered destaque">Colaborador</th>
                    <th class="bordered destaque">Quantidade</th>
                    <th class="bordered destaque">Custo Médio</th>
                    <th class="bordered destaque">Plano de Contas</th>
                    <th class="bordered destaque">Centro de Custos</th>
                    <th class="bordered destaque">Situação</th>
                    <th class="nobord"></th>
                </tr>
            @elseif(isset($requisicao->requisicao_id))
                <tr>
                    <td class="nobord"></td>
                    <td class="bordered">{{$requisicao->requisicao_id}}</td>
                    <td class="bordered">{{$requisicao->datahora}}</td>
                    <td class="bordered">{{$requisicao->colaborador}}</td>
                    <td class="bordered">{{$requisicao->quantidade}}</td>
                    <td class="bordered money">{{$requisicao->custo}}</td>
                    <td class="bordered">{{$requisicao->planoconta}}</td>
                    <td class="bordered">{{$requisicao->centrocusto}}</td>
                    <td class="bordered">{{$requisicao->situacao}}</td>
                    <td class="nobord"></td>
                </tr>
            @endif
            <!-- {{$count++}} -->
            @if($count == count($requisicoes) && count($requisicoes) > 1)
                <tr>
                    <td colspan="10"></td>
                </tr>
                <tr>
                    <th class="nobord"></th>
                    <th class="bordered destaque align-left" colspan="3">Total Geral</th>
                    <th class="bordered destaque">{{$requisicao->total}}</th>
                    <th class="bordered destaque money">{{$requisicao->custototal}}</th>
                    <th class="bordered destaque" colspan="3"></th>
                    <th class="nobord"></th>
                </tr>
            @endif
        @endforeach
        </table>
    @else
        <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
    @endif
</div>

@endsection