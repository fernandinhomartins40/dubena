@extends('layouts.contrato')

@section('content')

<style>
    table { border-spacing: 0px; border-collapse: collapse; text-align: left; }
    td, th {border: solid 0px; padding-right: 4px; padding-left: 4px; padding-top: 1px; padding-bottom: 1px;}
    .text-left { text-align: left;}
    .text-right { text-align: right;}
</style>

{{-- <br /> --}}
{{-- <br /> --}}
<div class="fontSize_14">
    <div style="color:#1D1D1F; margin-top: 1%">
        <div style="position: absolute">
            <div class="fontSize_15 negrito">
                {{ $empresa->razao_social }}
            </div>
            <div class="fontSize_13 negrito">
                {{ $empresa->rua->descricao }}, {{ $empresa->numero }}
                {{ $empresa->bairro->descricao }} -
                {{ $empresa->cidade->descricao }}/{{ $empresa->uf }}
            </div>
            <div class="fontSize_13 negrito">CNPJ: {{ $empresa->cnpj }} I.E.: {{ $empresa->inscricao_estadual }}</div>
        </div>
        <div style="position: absolute; float:right;">
            @if($empresa->logo != null)
            <img id="imgInicial" class="img-circle" style="max-height:50px; padding-top: -20px" src="data:image/png;base64,{{ $empresa->logo }}" alt="Logotipo"/>
            @else
            <img id="imgInicial" class="img-circle"  style="max-height:50px;" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo"/>
            @endif
        </div>
        <br />
        <br />
        <br />
        <div class="margTop_20" style="border:0.5px solid black; padding-top: 0px;"></div>
        <div style="text-align: center; margin-bottom: 1.5%; margin-top: 40px;padding-top:40px;" class="fontSize_14 margLeft_45 text-justify margTop_20 negrito">{{$titulo}}
        </div>
        <div style="margin-left: 0px;">
            <table id="tblReport">
                <tbody id="report-list" name="report-list">
                    <tr class="noborder">
                        <td class="noborder text-right" style="width:50%">Código da Compra:</td>
                        <td class="noborder text-left" style="width:50%">{{$nf->id}}</td>
                    </tr>
                    <tr class="noborder">
                        <td class="noborder text-right" style="width:50%">Data de Entrada/Saída:</td>
                        <td class="noborder text-left" style="width:50%">{{\Carbon\Carbon::parse($nf->datahoraentradasaida)->format('d/m/Y')}}</td>
                    </tr>
                    <tr class="noborder">
                        <td class="noborder text-right" style="width:50%">Número NF:</td>
                        <td class="noborder" style="width:50%">{{$nf->nfnumero}}</td>
                    </tr>
                    <tr class="noborder">
                        <td class="noborder text-right" style="width:50%">Série NF:</td>
                        <td class="noborder" style="width:50%">{{$nf->nfserie}}</td>
                    </tr>
                    <tr class="noborder">
                        <td class="noborder text-right" style="width:50%">Fornecedor:</td>
                        <td class="noborder" style="width:50%">{{$nf->cliente->nome}}</td>
                    </tr>
                    <tr class="noborder">
                        <td class="noborder text-right" style="width:50%">Operação:</td>
                        <td class="noborder" style="width:50%">{{$nf->descricaooperacao}}</td>
                    </tr>
                    <tr class="noborder">
                        <td class="noborder text-right" style="width:50%">Forma de Pagamento:</td>
                        <td class="noborder" style="width:50%">{{$nf->condicaoPagamento == null? "" : $nf->condicaoPagamento->descricao}}</td>
                    </tr>
                    <tr class="noborder">
                        <td class="noborder text-right" style="width:50%">Observação:</td>
                        <td class="noborder" style="width:50%">{{$nf->informacaocomplementar}}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="margTop_20" style="border:0.5px solid black; padding-top: 10px;"></div>
        <div style="text-align: center; margin-bottom: 1.5%" class="fontSize_14 margLeft_45 text-justify margTop_5 negrito">Itens da Compra
        </div>
        <div style="margin-left: 0px;">
            <table id="tblItens">
                <thead>
                    <tr class="noborder negrito">
                        <td class="noborder text-center" style="width:50px;">
                            Código
                        </td>
                        <td class="noborder" style="width:100px;">
                            Nome
                        </td>
                        <td class="noborder text-right" style="width:80px;">
                            Qtde
                        </td>
                        <td class="noborder">
                            Setor
                        </td>
                    </tr>
                </thead>
                <tbody id="reportItens-list" name="reportItens-list">
                    @foreach($nf->nfRecebidaItem as $item)
                    <tr class="noborder">
                        <td class="noborder text-center">
                            {{$item->produto->id}}
                        </td>
                        <td class="noborder">
                            {{$item->produto->descricao}}
                        </td>
                        <td class="noborder text-right">
                            {{$item->qcom}}
                        </td>
                        <td class="noborder">
                            {{$item->setor->descricao}}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="margTop_20" style="border:0.5px solid black; padding-top: 30px;"></div>
        <div style="text-align:center;padding-top:80px;">
            <div style="border-top: 1px solid black; width:40%; margin:0px auto;">
                Responsável pela Compra
            </div>
        </div>
        <div style="text-align:center;padding-top:80px;">
            <div style="border-top: 1px solid black; width:40%; margin:0px auto;">
                Responsável pelo Estoque
            </div>
        </div>
        <div style="text-align:center;padding-top:80px;">
            <div style="border-top: 1px solid black; width:40%; margin:0px auto;">
                Motorista/Fornecedor
            </div>
        </div>

    </div>
</div>
@endsection
