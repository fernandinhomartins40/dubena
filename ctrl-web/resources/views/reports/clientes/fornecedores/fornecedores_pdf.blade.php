@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @media print{@page{size:landscape;}}
</style>
<div style="font-size:10px;">
@if(isset($fornecedores) && count($fornecedores) > 0)
	<!-- {{$empresaAnterior = null}} -->
	<!-- {{$ufAnterior = null}} -->
    <!-- {{$cidadeAnterior = null}} -->
    <!-- {{$first = true}} -->
    <!-- {{$count = 0}} -->
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        <tbody id="repor-list" name="report-list">
            @foreach($fornecedores as $fornecedor)
                @if($fornecedor->empresa != $empresaAnterior)
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="8"><br /><hr /></td>
                        </tr>
                    @endif
                    <!-- {{$first = false}} -->
                    <tr>
                        <td colspan="3" class="fontSize15 negrito">{{$fornecedor->empresa}}</td>
                    </tr>
                @endif
                @if($fornecedor->estado != $ufAnterior || $fornecedor->empresa != $empresaAnterior)
                    <tr>
                        <td colspan="3" class="fontSize15">&nbsp;&nbsp;{{$fornecedor->estado}}</td>
                    </tr>
                @endif
                @if($fornecedor->cidade != $cidadeAnterior || $fornecedor->empresa != $empresaAnterior)
                    <tr>
                        <td colspan="3">&nbsp;&nbsp;&nbsp;&nbsp;{{$fornecedor->cidade}}</td>
                    </tr>
                    <tr>
                        <th class="nobord"></th>
                        <th class="bordered destaque">Cód</th>
                        <th class="bordered destaque align-left">Fornecedor</th>
                        <th class="bordered destaque">Tipo</th>
                        <th class="bordered destaque">CNPJ/CPF</th>
                        <th class="bordered destaque align-left">Telefone</th>
                        <th class="bordered destaque">Segmento</th>
                        <th class="nobord"></th>
                    </tr>
                @endif
                <tr>
                    <td class="nobord"></td>
                    <td class="bordered">{{$fornecedor->id}}</td>
                    <td class="bordered align-left">{{$fornecedor->razao_social}}</td>
                    <td class="bordered">{{$fornecedor->tipo}}</td>
                    <td class="bordered">{{$fornecedor->cnpj == null ? $fornecedor->cpf : $fornecedor->cnpj}}</td>
                    <td class="bordered align-left">{{$fornecedor->telefone}}</td>
                    <td class="bordered">{{$fornecedor->segmento}}</td>
                    <td class="nobord"></td>
                </tr>
                <!-- {{$ufAnterior = $fornecedor->estado}} -->
                <!-- {{$cidadeAnterior = $fornecedor->cidade}} -->
                <!-- {{$empresaAnterior = $fornecedor->empresa}} -->
                <!-- {{$count++}} -->
                @if($count == count($fornecedores))
                    <tr>
                        <td colspan="4"></td>
                    </tr>
                    <tr>
                        <th class="nobord"></th>
                        <th class="bordered destaque align-left" colspan="5">Total Geral</th>
                        <th class="bordered destaque">{{count($fornecedores)}}</th>
                        <th class="nobord"></th>
                    </tr>
                @endif
            @endforeach
        </tbody>
    <table>
@else
    <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
@endif
</div>

@endsection