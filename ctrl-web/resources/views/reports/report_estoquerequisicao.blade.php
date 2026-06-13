@extends('layouts.report')

@section('content')

<br/>
<br/>

<div class="fontSize_12">
    Cód Requisição: {{$codigo}}
    <br/>
    Data da Requisição: {{$dataRequisicao}}
    <br/>
    Autorizado por: {{$user}}
    <br/>
    Observações: {{$observacoes}}
    <br/>
    <br/>
    <br/>
        <table class="table table-condensed text-center">
            <thead class='bordered-top th'>
                <tr>
                    <td class='text-center table-bordered negrito'>Cód Produto</td>
                    <td class='text-center table-bordered negrito'>Produto</td>
                    <td class='text-center table-bordered negrito'>Setor Saída</td>
                    <td class='text-center table-bordered negrito'>Valor</td>
                    <td class='text-center table-bordered negrito'>Quantidade</td>
                </tr>
            </tdead>
            <tbody>
                @foreach ($estoquerequisicaoitems as $estoquerequisicaoitem)
                <tr class='' id="{{$estoquerequisicaoitem->id}}">
                    <td class='table-bordered'>{{$estoquerequisicaoitem->produto_id}}</td>
                    <td class='table-bordered'>{{$estoquerequisicaoitem->produto->descricao}}</td>
                    <td class='table-bordered'>{{$estoquerequisicaoitem->setor->descricao}}</td>
                    <td class='table-bordered'>{{requestNumeroDecimalOracle($estoquerequisicaoitem->customedio)}}</td>
                    <td class='table-bordered'>{{$estoquerequisicaoitem->quantidade}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    <div class="margLeft_45" style="position: absolute;text-align:left;padding-top:40px;">
        <div class="fleft" align="center" style="border-top: 1px solid black; width:35%">
            <div>Ass. Resp. Solicitante </div>
        </div>
    </div>
    <div class="" style="position: absolute;text-align:left;margin-left: 400px;padding-top:40px;">
        <div class="fleft" align="center" style="border-top: 1px solid black; width:80%">
            <div>Ass. Resp. Estoque </div>
        </div>
    </div>
</div>



@endsection
