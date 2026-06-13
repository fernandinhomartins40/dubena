@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
<!-- {{$first = true}} -->
@if(isset($checklists) && count($checklists) > 0)
    <table style="margin-top:7px; margin-left: -5px;width:65%">
        @foreach($checklists as $check)
            @if(isset($check->observacao))
                <tr>
                    <td style="width:5%"></td>
                    <td class="bordered">Observações</td>
                    <td class="bordered">{{$check->observacao}}</td>
                    <td></td>
                </tr>
            @elseif(isset($check->empresa_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="4" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="4" class="top-style fontSize15 nobord">{{$check->empresa}}</td>
                </tr>
                <!-- {{$first = false}} -->
            @elseif(isset($check->pesquisa_id))
                <tr>
                    <td colspan="4"></td>
                </tr>
                <tr>
                    <td class="nobord" colspan="4" style="font-size:13.9px;padding:4px 0px;font-style:;">&nbsp;&nbsp;{{$check->data}}</td>
                </tr>
            @elseif(isset($check->topico_id))
                <tr>
                    <td colspan="4"></td>
                </tr>
                <tr>
                    <td colspan="2" class="nobord" style="font-size:12px;">&nbsp;&nbsp;{{$check->topico}}</td>
                </tr>
            @elseif(isset($check->pergunta_id))
                <tr>
                    <td colspan="4"></td>
                </tr>
                <tr>
                    <th style="width:5%"></th>
                    <th class="bordered destaque" style="font-size:11.3px;" colspan="2">{{$check->pergunta}}</th>
                    <th></th>
                </tr>
            @elseif(isset($check->resposta_id))
                @if($check->tipopergunta == "4" || $check->tipopergunta == "0")
                    <tr>
                        <td style="width:5%"></td>
                        <td class="bordered" style="width:30%;">{{$check->respostas}}</td>
                        @if($check->respondida != null)
                            <td class="bordered align-left" style=""><i class="fa fa-check" aria-hidden="true"></i></td>
                        @else
                            <td class="bordered"></td>
                        @endif
                        <td></td>
                    </tr>
                @elseif($check->tipopergunta == "3")
                    <tr>
                        <td style="width:5%"></td>
                        <td class="bordered" style="width:30%;">{{$check->respostas}}</td>
                        <td class="bordered">{{requestDataOracle($check->check_resposta)}}</td>
                        <td></td>
                    </tr>
                @else
                    <tr>
                        <td style="width:5%"></td>
                        <td class="bordered" style="width:30%;">{{$check->respostas}}</td>
                        @if($check->respondida != null)
                            <td class="bordered">{{$check->check_resposta}}</td>
                        @else
                            <td class="bordered"></td>
                        @endif
                        <td></td>
                    </tr>
                @endif
            @endif
        @endforeach
    </table>
@else
    <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection