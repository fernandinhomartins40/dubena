@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @media print{@page{size:landscape;}}
</style>
<div class="fontSize_12">
<!-- {{$first = true}} -->
    @if(isset($clientes) && count($clientes) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($clientes as $cliente)
                @if(isset($cliente->totalcliente))
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Total do Segmento</th>
                        <th class="bordered destaque">{{$cliente->totalcliente}}</th>
                        <th class="bordered destaque" colspan="2"></th>
                        <th></th>
                    </tr>
                @elseif(isset($cliente->segmento_id))
                    @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8" class="nobord"><br /><hr /></td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="4" class="top-style fontSize15 nobord">Segmento: {{$cliente->segmento}}</td>
                    </tr>
                    <!-- {{$first = false}} -->
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Cliente</th>
                        <th class="bordered destaque align-left">Endereço</th>
                        <th class="bordered destaque align-left">Telefone</th>
                        <th class="bordered destaque">Setor</th>
                        <th></th>
                    </tr>
                @elseif(isset($cliente->nome))
                    <tr>
                        <td></td>
                        <td class="bordered align-left">{{$cliente->nome}}</td>
                        <td class="bordered align-left">{{$cliente->endereco}}</td>
                        <td class="bordered align-left">{{$cliente->telefone}}</td>
                        <td class="bordered">{{$cliente->setor}}</td>
                        <td></td>
                    </tr>
                @endif
                @if ($loop->last)
                    <tr>
                        <td colspan="6"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Total Geral</th>
                        <th class="bordered destaque">{{$cliente->total}}</th>
                        <th class="bordered destaque" colspan="2"></th>
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