@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize13">
<!-- {{$first = true}} -->
@if(isset($lancamentos) && count($lancamentos) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($lancamentos as $lancamento)
            @if(isset($lancamento->total))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="3">Total</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($lancamento->total)}}</th>
                    <th></th>
                </tr>
            @elseif(isset($lancamento->plano))
                <tr>
                    <td colspan="6" class="nobord fontSize_13 negrito">Centro de Custos: {{$lancamento->plano}}</td>
                </tr>
            @elseif(isset($lancamento->fornecedor_id))
                <tr>
                    <td colspan="6"></td>
                </tr>
                <tr>
                    <td colspan="6" class="top-style fontSize_12 nobord">{{$lancamento->pagar == "P" ? "Fornecedor" : "Cliente"}}: {{$lancamento->fornecedor}}</td>
                </tr>
                <tr>
                    <td colspan="6"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque">Data</th>
                    <th class="bordered destaque">Código</th>
                    <th class="bordered destaque align-left">Descrição</th>
                    <th class="bordered destaque money">Valor</th>
                    <th></th>
                </tr>
            @elseif(isset($lancamento->data))
                <tr>
                    <td></td>
                    <td class="bordered">{{$lancamento->data}}</td>
                    <td class="bordered">{{$lancamento->codigo}}</td>
                    <td class="bordered align-left">{{$lancamento->descricao}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($lancamento->valor)}}</td>
                    <td></td>
                </tr>
            @endif
            @if ($loop->last)
                <tr>
                    <td colspan="4"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="3">Total Geral</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($lancamento->totalgeral)}}</th>
                    <th></th>
                </tr>
            @endif
            
        @endforeach
    </table>
@else
    <p style="text-align:center;font-size:12px;"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection