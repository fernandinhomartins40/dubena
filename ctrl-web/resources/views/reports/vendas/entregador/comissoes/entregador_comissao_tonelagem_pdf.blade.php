@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @media print{@page{size:portrait;}}
</style>
<div class="fontSize11">
<!-- {{$first = true}} -->
    @if(isset($comissoes) && count($comissoes) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($comissoes as $comissao)
                @if(isset($comissao->colquantidade))
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="3">Total do Colaborador</th>
                        <th class="bordered destaque">{{$comissao->colquantidade}}</th>
                        <th class="bordered destaque">{{$comissao->colkg}}</th>
                        <th class="bordered destaque money"></th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($comissao->colpercentual)}}</th>
                        <th></th>
                    </tr>
                    <tr>
                        <td colspan="10"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="nobord" colspan="5"></th>
                        <th class="bordered destaque">Valor Pagar</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($comissao->coltotal)}}</th>
                        <th></th>
                    </tr>
                @elseif(isset($comissao->colaborador))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="10" class="nobord"><br /><hr /></td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="10" class="top-style fontSize15 nobord">Colaborador: {{$comissao->colaborador}}</td>
                    </tr>
                    <!-- {{$first = false}} -->
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Cód.</th>
                        <th class="bordered destaque">Data</th>
                        <th class="bordered destaque">Produto</th>
                        <th class="bordered destaque">Qtde</th>
                        <th class="bordered destaque">Kg</th>
                        <th class="bordered destaque money">{{$resumo=='1'?'':'Percentual'}}</th>
                        <th class="bordered destaque money">Com. Percentual</th>
                        <th></th>
                    </tr>
                @elseif(isset($comissao->pedido_id))
                    <tr>
                        <td></td>
                        <td class="bordered">{{$comissao->pedido_id}}</td>
                        <td class="bordered">{{$comissao->data}}</td>
                        <td class="bordered">{{$comissao->produto}}</td>
                        <td class="bordered">{{$comissao->quantidade}}</td>
                        <td class="bordered">{{$comissao->kg}}</td>
                        <td class="bordered money">{{$resumo=='1'?'':requestPercentualOracle($comissao->percentual_comissao)}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($comissao->percentual)}}</td>
                        <td></td>
                    </tr>
                @endif
                @if($loop->last)
                    <tr>
                        <td colspan="10"></td>
                    </tr>
                    <!--
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="3">Total Geral</th>
                        <th class="bordered destaque">{{$comissao->quantidadetotal}}</th>
                        <th class="bordered destaque">{{$comissao->kgtotal}}</th>
                        <th class="bordered destaque money"></th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($comissao->percentualtotal)}}</th>
                        <th></th>
                    </tr>
                    <tr>
                        <td colspan="10"></td>
                    </tr>
                    -->
                    <tr>
                        <th></th>
                        <th class="nobord" colspan="5"></th>
                        <th class="bordered destaque">Total a Pagar</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($comissao->totalgeral)}}</th>
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