@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize13">
<!-- {{$first = true}} -->
@if(isset($interacoes) && count($interacoes) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($interacoes as $interacao)
            @if(isset($interacao->total))
                <tr>
                    <td colspan="8"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="5">Total</th>
                    <th class="bordered destaque">{{$interacao->total}}</th>
                    <th></th>
                </tr>
            @elseif(isset($interacao->empresa_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="8" class="top-style fontSize15 nobord">Empresa: {{$interacao->empresa}}</td>
                </tr>
                <!-- {{$first = false}} -->
            @elseif(isset($interacao->data))
                <tr>
                    <td colspan="8"></td>
                </tr>
                <tr>
                    <th></td>
                    <th class="bordered destaque" colspan="3">Data: {{$interacao->data}}</th>
                    <th class="bordered destaque" colspan="2">Tipo: {{$interacao->tipo}}</th>
                    <th class="bordered destaque">Situação: {{$interacao->situacao}}</th>
                    <th></th>
                </tr>
                <tr>
                    <td></td>
                    <td class="bordered align-left" colspan="3">Cliente: {{$interacao->cliente}}</td>
                    <td class="bordered" colspan="2">Segmento: {{$interacao->segmento}}</td>
                    <td class="bordered align-left">End.: {{$interacao->endereco}}</td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td class="bordered align-left" colspan="6">Descrição: {{$interacao->descricao}}</td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td class="bordered align-left" colspan="6">Ação: {{$interacao->acao}}</td>
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