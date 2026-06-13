@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize14">
<!-- {{$first = true}} -->
    @if(isset($convenios) && count($convenios) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($convenios as $convenio)
                @if(isset($convenio->totalsetbruto))
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="5">Total</th>
                        <th class="bordered destaque">{{$convenio->quantidadetot}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($convenio->totalliquido)}}</th>
                        <th></th>
                    </tr>
                @elseif(isset($convenio->totalquantidade))
                    <tr>
                        <td colspan="8"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="5">Total Conv.</th>
                        <th class="bordered destaque">{{$convenio->totalquantidade}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($convenio->totalliquido)}}</th>
                        <th></th>
                    </tr>
                @elseif(isset($convenio->convenio))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="8" class="nobord"><br /><hr /></td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="8" class="top-style fontSize15 nobord">Convênio: {{$convenio->convenio}}</td>
                    </tr>
                    <!-- {{$first = false}} -->
                @elseif(isset($convenio->setor))
                    <tr>
                        <td colspan="8" style="padding:8px 0px" class="nobord">&nbsp;&nbsp;&nbsp;&nbsp;Setor: {{$convenio->setor}}</td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Cliente</th>
                        <th class="bordered destaque">Pedido</th>
                        <th class="bordered destaque">Data</th>
                        <th class="bordered destaque align-left">Forma Pagamento</th>
                        <th class="bordered destaque">Produto</th>
                        <th class="bordered destaque">Qtd.</th>
                        <th class="bordered destaque money">Valor</th>
                        <th></th>
                    </tr>
                @elseif(isset($convenio->pedido_id))
                    <tr>
                        <td></td>
                        <td class="bordered align-left">{{$convenio->cliente}}</td>
                        <td class="bordered">{{$convenio->pedido_id}}</td>
                        <td class="bordered">{{$convenio->datahoraacao}}</td>
                        <td class="bordered align-left">{{$convenio->forma_pagamento}}</td>
                        <td class="bordered">{{$convenio->produto}}</td>
                        <td class="bordered">{{$convenio->quantidade}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($convenio->valorliquido)}}</td>
                        <td></td>
                    </tr>                    
                @endif
                @if ($loop->last)
                    <tr>
                        <td colspan="8"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="5">Total Geral</th>
                        <th class="bordered destaque">{{$convenio->quantidadetotal}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($convenio->totalgeraliquido)}}</th>
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