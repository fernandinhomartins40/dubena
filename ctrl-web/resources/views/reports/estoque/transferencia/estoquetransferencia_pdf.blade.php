@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
<!-- {{$first = true}} -->
<!-- {{$count = 0}} -->
    @if(isset($origem) && count($origem) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($origem as $org)
                @if(isset($org->total))
                    <tr>
                        <td colspan="7"></td>
                    </tr>
                    <tr>
                        <th class="nobord"></th>
                        <th class="bordered destaque align-left" colspan="3">Total Geral</th>
                        <th class="bordered destaque">{{$org->total}}</th>
                        <th class="bordered destaque"></th>
                        <th class="nobord"></th>
                    </tr>
                @elseif(isset($org->totalrecebido))
                    <tr>
                        <th class="nobord"></th>
                        <th class="bordered destaque align-left" colspan="3">Total Recebido</th>
                        <th class="bordered destaque">{{$org->totalrecebido}}</th>
                        <th class="bordered destaque"></th>
                        <th class="nobord"></th>
                    </tr>                    
                @elseif(isset($org->origem_id))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="7"><br /><hr /></td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="2" class="top-style fontSize15">Origem: {{$org->origem}}</td>
                    </tr>
                <!-- {{$first = false}} -->
                @elseif(isset($org->destino_id))
                    <tr>
                        <td colspan="7">&nbsp;&nbsp;&nbsp;&nbsp;Destino: {{$org->destino}}</td>
                    </tr>
                    <tr>
                        <td class="nobord"></td>
                        <th class="bordered destaque">Cód. Transferência</th>
                        <th class="bordered destaque">Data/Hora</th>
                        <th class="bordered destaque">Produto</th>
                        <th class="bordered destaque">Quantidade</th>
                        <th class="bordered destaque">Observações</th>
                        <th class="nobord"></th>
                    </tr>
                @elseif(isset($org->produto))
                    <tr>
                        <td class="nobord"></td>
                        <td class="bordered">{{$org->transferencia_id}}</td>
                        <td class="bordered">{{$org->datahora}}</td>
                        <td class="bordered">{{$org->produto}}</td>
                        <td class="bordered">{{$org->quantidade}}</td>
                        <td class="bordered">{{$org->observacoes}}</td>
                        <th class="nobord"></th>
                    </tr>
                @endif
                <!-- {{$count++}} -->
            @endforeach
        </table>
    @else
        <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
    @endif
</div>

@endsection