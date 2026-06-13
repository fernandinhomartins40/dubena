@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
<!-- {{$first = true}} -->
@if(isset($posvenda) && count($posvenda) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($posvenda as $venda)
            @if(isset($venda->pesquisa_id))
                <tr>
                    <td></td>
                    <td class="bordered">{{$venda->cliente_id}}</td>
                    <td class="bordered align-left">{{$venda->cliente}}</td>
                    <td class="bordered align-left" colspan="7">{{$venda->observacao}}</td>
                    <td></td>
                </tr>
            @elseif(isset($venda->cabecalho))                    
                <tr>
                    <td colspan="9"></td>
                </tr>
                <tr>
                    <td class="nobord" colspan="9" style="font-size:13.3px;font-weight:600;">Observações de Clientes</td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque">Cód. Cliente</th>
                    <th class="bordered destaque align-left">cliente</th>
                    <th class="bordered destaque align-left" colspan="7">Observação</th>
                    <th></th>
                </tr>
            @elseif(isset($venda->empresa_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="12" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="9" class="top-style fontSize15 nobord">Empresa: {{$venda->empresa}}</td>
                </tr>
                <!-- {{$first = false}} -->
            @elseif(isset($venda->posvenda_id))
                <tr>
                    <td colspan="10"></td>
                </tr>
                <tr>
                    <td colspan="9" class="nobord" style="font-size:13px;font-weight:600;">&nbsp;&nbsp;Pós-Venda: {{$venda->posvenda}}</td>
                </tr>
            @elseif(isset($venda->pergunta_id))
                <tr>
                    <td colspan="9" class="nobord" style="padding:7px 0;">&nbsp;&nbsp;&nbsp;&nbsp;{{$venda->pergunta}}</td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque">Resposta</th>
                    <th class="bordered destaque">Quantidade</th>
                    <th class="bordered destaque">%</th>
                    <th></th>
                </tr>
            @elseif(isset($venda->resposta_id))
                <tr>
                    <td></td>
                    <td class="bordered">{{$venda->resposta}}</td>
                    <td class="bordered">{{$venda->quantidade}}</td>
                    <td class="bordered">{{$venda->percentual}}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endif
        @endforeach
    </table>
@else
    <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection