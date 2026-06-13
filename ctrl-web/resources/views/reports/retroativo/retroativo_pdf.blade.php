@extends('layouts.reportNoBootstrap')

@section('content')
<!-- {{$first = true}} -->
<div class="fontSize_11">
    @if(isset($retroativos) && count($retroativos) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($retroativos as $retro)
                @if (isset($retro->empresa_id))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="9"><br /><hr /></td>
                        </tr>
                    @endif
                    <!-- {{$first = false}} -->
                    <tr>
                        <td colspan="4" class="negrito" style="font-size: 13px; padding-left: -15px">Empresa: {{$retro->empresa}}</td>
                    </tr>
                @elseif (isset($retro->user_id))
                    <tr>
                        <td colspan="5"></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="negrito" style="font-size: 13px; padding-left: -15px">&nbsp;&nbsp;Usuário: {{$retro->user}}</td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Data</th>
                        <th class="bordered destaque align-left">Conta</th>
                        <th class="bordered destaque">Movimentação</th>
                        <th class="bordered destaque">Origem</th>
                        <th class="bordered destaque">Lançamento</th>
                        <th class="bordered destaque">Descrição</th>
                        <th class="bordered destaque money">Valor</th>
                        <th></th>
                    </tr>
                @elseif (isset($retro->data))
                    <tr>
                        <td></td>
                        <td class="bordered align-left">{{$retro->data}}</td>
                        <td class="bordered align-left">{{$retro->conta}}</td>
                        <td class="bordered">{{$retro->pagarreceber}}</td>
                        <td class="bordered">{{$retro->origem}}</td>
                        <td class="bordered">{{$retro->lancamento}}</td>
                        <td class="bordered align-left">{{$retro->descricao}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($retro->valor)}}</td>
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