@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize12">
<!-- {{$first = true}} -->
@if(isset($valegas) && count($valegas) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($valegas as $vale)
            @if(isset($vale->quantidade))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="2">Total</th>
                    <th class="bordered destaque">{{$vale->quantidade}}</th>
                    <th class="bordered destaque" colspan="2"></th>
                    <th></th>
                </tr>
            @elseif(isset($vale->setor_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td></td>
                    <td colspan="5" class="top-style fontSize14 nobord">{{$vale->setor}}</td>
                </tr>
                <!-- {{$first = false}} -->
                <tr>
                    <th></th>
                    <th class="bordered destaque">Data</th>
                    <th class="bordered destaque">Cliente</th>
                    <th class="bordered destaque">Endereço</th>
                    <th class="bordered destaque">Telefone</th>
                    <th class="bordered destaque">Produto</th>
                    <th class="bordered destaque">PDV</th>
                    <!--
                    <th class="bordered destaque">Vale Gás</th>
                    -->
                    <th></th>
                </tr>
            @elseif(isset($vale->valegas))
                <tr>
                    <td></td>
                    <td class="bordered">{{$vale->datapedido}}</td>
                    <td class="bordered">{{$vale->cliente}}</td>
                    <td class="bordered">{{$vale->endereco}}</td>
                    <td class="bordered">{{$vale->telefone}}</td>
                    <td class="bordered">{{$vale->produto}}</td>
                    <td class="bordered">{{$vale->pdv}}</td>
                    <!--
                    <td class="bordered">{{$vale->valegas}}</td>
                    -->
                    <td></td>
                </tr>                
            @endif
            @if ($loop->last)
                <tr>
                    <td colspan="7"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="2">Total Geral</th>
                    <th class="bordered destaque">{{$vale->quantidadetotal}}</th>
                    <th class="bordered destaque" colspan="2"></th>
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