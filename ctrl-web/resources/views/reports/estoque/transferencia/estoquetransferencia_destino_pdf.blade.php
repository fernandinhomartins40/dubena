@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
	<!-- {{$first = true}} -->
<!-- {{$count = 0}} -->
    @if(isset($destino) && count($destino) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($destino as $dest)
                @if(isset($dest->total))
                    <tr>
                        <td colspan="7"></td>
                    </tr>
                    <tr>
                        <th class="nobord"></th>
                        <th class="bordered destaque align-left" colspan="3">Total Geral</th>
                        <th class="bordered destaque">{{$dest->total}}</th>
                        <th class="bordered destaque"></th>
                        <th class="nobord"></th>
                    </tr>
                @elseif(isset($dest->totalrecebido))
                    <tr>
                        <th class="nobord"></th>
                        <th class="bordered destaque align-left" colspan="3">Total Enviado</th>
                        <th class="bordered destaque">{{$dest->totalrecebido}}</th>
                        <th class="bordered destaque"></th>
                        <th class="nobord"></th>
                    </tr>                    
                @elseif(isset($dest->destino_id))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="7"><br /><hr /></td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="3" class="top-style fontSize15">Destino: {{$dest->destino}}</td>
                        <td colspan="4"></td>
                    </tr>
                <!-- {{$first = false}} -->
                @elseif(isset($dest->origem_id))
                    <tr>
                        <td colspan="7" style="padding-top:4px;padding-bottom:4px;">&nbsp;&nbsp;&nbsp;&nbsp;Origem: {{$dest->origem}}</td>
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
                @elseif(isset($dest->transferencia_id))
                    <tr>
                        <td class="nobord"></td>
                        <td class="bordered">{{$dest->transferencia_id}}</td>
                        <td class="bordered">{{$dest->datahora}}</td>
                        <td class="bordered">{{$dest->produto}}</td>
                        <td class="bordered">{{$dest->quantidade}}</td>
                        <td class="bordered">{{$dest->observacoes}}</td>
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