@extends('layouts.reportNoBootstrapNoSession')

@section('content')

<style>
.style-9 {
    color: black;
    padding-left: 5pt;
    padding-right: 5pt;
    font-size: 16pt;
    font-family: "Arial";
    font-weight: normal;
    font-style: normal;
    text-decoration: none;
    text-align: center;
    word-spacing: 0pt;
    letter-spacing: 0pt;
    white-space: pre-wrap;
    border: 1pt solid #b2a79a;
    background-color: #cccccc;
}

.style-11 {
    color: #003333;
    padding-left: 5pt;
    padding-right: 5pt;
    font-size: 14pt;
    font-family: "Arial";
    font-weight: normal;
    font-style: normal;
    text-decoration: none;
    text-align: center;
    word-spacing: 0pt;
    letter-spacing: 0pt;
    white-space: pre-wrap;
    border: 1pt solid #b2a79a;
    background-color: white;
}

.style-12 {
    color: #003333;
    padding-left: 5pt;
    padding-right: 5pt;
    font-size: 24pt;
    font-family: "Arial";
    font-weight: bold;
    font-style: normal;
    text-decoration: none;
    text-align: center;
    word-spacing: 0pt;
    letter-spacing: 0pt;
    white-space: pre-wrap;
    border: 1pt solid #b2a79a;
    background-color: white;
}

.style-13 {
    color: #003333;
    padding-left: 5pt;
    padding-right: 5pt;
    font-size: 16pt;
    font-family: "Arial";
    font-weight: normal;
    font-style: normal;
    text-decoration: none;
    text-align: center;
    word-spacing: 0pt;
    letter-spacing: 0pt;
    white-space: pre-wrap;
    border: 1pt solid #b2a79a;
    background-color: white;
}
.style-12 {
    color: #003333;
    padding-left: 5pt;
    padding-right: 5pt;
    font-size: 24pt;
    font-family: "Arial";
    font-weight: bold;
    font-style: normal;
    text-decoration: none;
    text-align: center;
    word-spacing: 0pt;
    letter-spacing: 0pt;
    white-space: pre-wrap;
    border: 1pt solid #b2a79a;
    background-color: white;
}

.style-17 {
    color: white;
    font-size: 54pt;
    font-family: "Arial";
    font-weight: normal;
    font-style: normal;
    text-decoration: none;
    text-align: center;
    word-spacing: 0pt;
    letter-spacing: 0pt;
    white-space: pre-wrap;
    border: 1pt solid black;
    background-color: #333333;
}

.style-geral {
    font-family: 'Helvetica' !important;
}

</style>

<div class="fontSize_12 style-geral">
@if(isset($vendas) && count($vendas))
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        <tr>
            <th></th>
            <th class="bordered style-9">Setor</th>
            <th class="bordered style-9">Colaborador</th>
            <th class="bordered style-9">Meta</th>
            <th class="bordered style-9">Quant</th>
            <th></th>
        </tr>
        @foreach($vendas as $venda)
            <tr>
                <td></td>
                <td class="bordered style-11">{{$venda->setor}}</td>
                <td class="bordered style-12">{{$venda->nome}}</td>
                <td class="bordered style-13">{{$venda->qtdemeta}}</td>
                <td class="bordered style-12">{{$venda->qtde}}</td>
                <td><img  style="height: 38px; width: auto;" src="data:image/png;base64,{{$venda->qtdemeta<=$venda->qtde?$seta_cima:$seta_baixo}}"/></td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="text-align:right; font-size: 16pt;">Total Geral</td>
            <td class="bordered style-13">{{$totais[1]}}</td>
            <td class="bordered style-17">{{$totais[0]}}</td>
            <td></td>
        </tr>
    </table>
@else
    <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection