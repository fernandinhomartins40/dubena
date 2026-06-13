@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @media print{@page{size:landscape;}}
</style>
<!-- {{$first = true}} -->
<div class="{{$fonte}}">
    @if(isset($nfs) && count($nfs) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($nfs as $nf)
                @if(isset($nf->total))
                    <tr>
                        <td></td>
                        <th class="bordered destaque align-left">Total</th>
                        <th class="bordered destaque">{{$nf->quant}}</th>
                        <th colspan="4" class="bordered destaque money">{{requestNumeroDecimalOracle($nf->total)}}</th>
                        <td></td>
                    </tr>
                @elseif(isset($nf->principal))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="8"><br /><hr /></td>
                        </tr>
                    @endif
                    <!-- {{$first = false}} -->
                    <tr>
                        <td colspan="7" class="negrito" style="font-size: 13px; padding-left: -15px">{{$nf->tipo}}: {{$nf->principal}}</td>
                    </tr>

                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">{{$nf->sec}}</th>
                        <th class="bordered destaque">Modelo</th>
                        <th class="bordered destaque">Número</th>
                        <th class="bordered destaque">Série</th>
                        <th class="bordered destaque align-left">Emitente/Destinatário</th>
                        <th class="bordered destaque money">Valor</th>
                        <th></th>
                    </tr>
                @elseif(isset($nf->secundario))
                    <tr>
                        <td></td>
                        <td class="bordered align-left">{{$nf->secundario}}</td>
                        <td class="bordered">{{$nf->modelo}}</td>
                        <td class="bordered">{{$nf->numero}}</td>
                        <td class="bordered">{{$nf->serie}}</td>
                        <td class="bordered align-left">{{$nf->destinatario}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($nf->valor)}}</td>
                        <td></td>
                    </tr>
                @endif
                @if ($loop->last)
                    <tr>
                        <td colspan="8"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <th class="bordered destaque align-left">Total Geral</th>
                        <th class="bordered destaque">{{$nf->quantidade}}</th>
                        <th colspan="4" class="bordered destaque money">{{requestNumeroDecimalOracle($nf->totalgeral)}}</th>
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