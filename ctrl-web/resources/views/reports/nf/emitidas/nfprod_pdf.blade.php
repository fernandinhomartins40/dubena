@extends('layouts.reportNoBootstrap')

@section('content')
<!-- {{$first = true}} -->
<div class="{{$fonte}}">
    @if(isset($nfs) && count($nfs) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;width:100%;">
            @foreach($nfs as $nf)
                @if(isset($nf->qantsaidas))
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Total</th>
                        <th class="bordered destaque" colspan="4"></th>
                        <th class="bordered destaque">Saída</th>
                        <th class="bordered destaque">{{number_format($nf->qantsaidas,2,'.',',')}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($nf->saidas)}}</th>
                        <th></th>
                    </tr>
                    @if($nf->entradas > 0)
                        <tr>
                            <th></th>
                            <th class="bordered destaque" colspan="5"></th>
                            <th class="bordered destaque">Entrada</th>
                            <th class="bordered destaque">{{number_format($nf->qantentradas,2,'.',',')}}</th>
                            <th class="bordered destaque money">{{requestNumeroDecimalOracle($nf->entradas)}}</th>
                            <th></th>
                        </tr>
                    @endif
                @elseif(isset($nf->produto_id))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="10"><br /><hr /></td>
                        </tr>
                    @endif
                    <!-- {{$first = false}} -->
                    <tr>
                        <td colspan="4" class="negrito" style="font-size: 13px; padding-left: -15px">{{$nf->produto}}</td>
                    </tr>

                    <tr>
                        <td></td>
                        <th class="bordered destaque">Data Emissão</th>
                        <th class="bordered destaque">Modelo</th>
                        <th class="bordered destaque">Número</th>
                        <th class="bordered destaque">Série</th>
                        <th class="bordered destaque">Operação</th>
                        <th class="bordered destaque">E/S</th>
                        <th class="bordered destaque">Quantidade</th>
                        <th class="bordered destaque money">Valor</th>
                        <td></td>
                    </tr>
                @elseif(isset($nf->data))
                    <tr>
                        <td></td>
                        <td class="bordered">{{$nf->data}}</td>
                        <td class="bordered">{{$nf->modelo}}</td>
                        <td class="bordered">{{$nf->numero}}</td>
                        <td class="bordered">{{$nf->serie}}</td>
                        <td class="bordered">{{$nf->cfop}}</td>
                        <td class="bordered">{{$nf->tipo}}</td>
                        <td class="bordered">{{number_format($nf->quantidade,2,'.',',')}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($nf->valor)}}</td>
                        <td></td>
                    </tr>
                @endif
                @if ($loop->last && isset($nf->quantsaidas))
                    <tr>
                        <td colspan="10"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Total Geral</th>
                        <th class="bordered destaque" colspan="4"></th>
                        <th class="bordered destaque">Saída</th>
                        <th class="bordered destaque">{{number_format($nf->quantsaidas,2,'.',',')}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($nf->saidageral)}}</th>
                        <th></th>
                    </tr>
                    @if($nf->entradageral > 0)
                        <tr>
                            <th></th>
                            <th class="bordered destaque" colspan="5"></th>
                            <th class="bordered destaque">Entrada</th>
                            <th class="bordered destaque">{{number_format($nf->quantentradas,2,'.',',')}}</th>
                            <th class="bordered destaque money">{{requestNumeroDecimalOracle($nf->entradageral)}}</th>
                            <th></th>
                        </tr>
                    @endif
                @endif
            @endforeach
        </table>
    @else
        <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
    @endif
</div>
@endsection