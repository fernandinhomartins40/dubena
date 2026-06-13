@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize14">
<!-- {{$first = true}} -->
    @if(isset($entregadores) && count($entregadores) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($entregadores as $entregador)
                @if(isset($entregador->quantotal))
                    <tr>
                        <td colspan="8"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="2">Total do Entregador</th>
                        <th class="bordered destaque">{{$entregador->quantotal}}</th>
                        <th class="bordered destaque money" colspan="2">{{requestNumeroDecimalOracle($entregador->totalcol)}}</th>
                        <th></th>
                    </tr>
                @elseif(isset($entregador->quantidadetotal))
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="2">Total da Operação</th>
                        <th class="bordered destaque">{{$entregador->quantidadetotal}}</th>
                        <th class="bordered destaque money" colspan="2">{{requestNumeroDecimalOracle($entregador->totalop)}}</th>
                        <th></th>
                    </tr>
                @elseif(isset($entregador->colaborador_id))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="8" class="nobord"><br /><hr /></td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="8" class="top-style fontSize15 nobord">Colaborador: {{$entregador->colaborador}}</td>
                    </tr>
                    <!-- {{$first = false}} -->
                @elseif(isset($entregador->operacao_id))
                    <tr>
                        <td colspan="8" style="padding:8px 0px" class="nobord">&nbsp;&nbsp;&nbsp;&nbsp;Operação: {{$entregador->operacao}}</td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Cód. Pedido</th>
                        <th class="bordered destaque">Produto</th>
                        <th class="bordered destaque">Quantidade</th>
                        <th class="bordered destaque money">Valor</th>
                        <th class="bordered destaque money">Desconto</th>
                        <th></th>
                    </tr>
                @elseif(isset($entregador->produto))
                    <tr>
                        <td></td>
                        <td class="bordered">{{$entregador->pedido_id}}</td>
                        <td class="bordered">{{$entregador->produto}}</td>
                        <td class="bordered">{{$entregador->quantidade}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($entregador->valor)}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($entregador->desconto)}}</td>
                        <td></td>
                    </tr>                    
                @endif
                @if($loop->last)
                    <tr>
                        <td colspan="8"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="2">Total Geral</th>
                        <th class="bordered destaque">{{$entregador->qtdtotal}}</th>
                        <th class="bordered destaque money" colspan="2">{{requestNumeroDecimalOracle($entregador->total)}}</th>
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