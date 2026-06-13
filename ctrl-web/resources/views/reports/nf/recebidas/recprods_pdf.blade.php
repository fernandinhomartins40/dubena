@extends('layouts.reportNoBootstrap')

@section('content')
<!-- {{$first = true}} -->
<div class="font_size12">
    @if(isset($documentos) && count($documentos) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($documentos as $doc)
                @if(isset($doc->total))
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="5">Total</th>
                        <th class="bordered destaque">{{$doc->quant}}</th>
                        <th class="bordered destaque"></th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($doc->total)}}</th>
                        <th></th>
                    </tr>                    
                @elseif(isset($doc->produto_id))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="10"><br /><hr /></td>
                        </tr>
                    @endif
                    <!-- {{$first = false}} -->
                    <tr>
                        <td colspan="5" class="negrito" style="font-size: 13px; padding-left: -15px">Produto: {{$doc->produto}}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <th class="bordered destaque align-left">Data Emissão</th>
                        <th class="bordered destaque align-left">Emitente</th>
                        <th class="bordered destaque">Número</th>
                        <th class="bordered destaque">Série</th>
                        <th class="bordered destaque">Operação</th>
                        <th class="bordered destaque">Qtde.</th>
                        <th class="bordered destaque money">Valor Unitário</th>
                        <th class="bordered destaque money">Valor</th>
                        <td></td>
                    </tr>
                @elseif(isset($doc->data))
                    <tr>
                        <td></td>
                        <td class="bordered align-left">{{$doc->data}}</td>
                        <td class="bordered align-left">{{$doc->emitente}}</td>
                        <td class="bordered">{{$doc->numero}}</td>
                        <td class="bordered">{{$doc->serie}}</td>
                        <td class="bordered">{{$doc->cfop}}</td>
                        <td class="bordered">{{$doc->quant}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($doc->unitario)}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($doc->valor)}}</td>
                        <td></td>
                    </tr>
                @endif
                @if ($loop->last && isset($doc->quantgeral))
                    <tr>
                        <td colspan="10"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="5">Total Geral</th>
                        <th class="bordered destaque">{{$doc->quantgeral}}</th>
                        <th class="bordered destaque"></th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($doc->totalgeral)}}</th>
                        <th></th>
                    </tr>
                @endif
            @endforeach
        </table>
    @else
        <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
    @endif
</div>
@endsection