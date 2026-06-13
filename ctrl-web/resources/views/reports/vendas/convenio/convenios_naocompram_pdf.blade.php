@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @media print{@page{size:landscape;}}
</style>
<div class="fontSize13">
<!-- {{$first = true}} -->
    @if(isset($clientes) && count($clientes) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($clientes as $cliente)
            @if(isset($cliente->total))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="2">Total Conveniados</th>
                    <th class="bordered destaque">{{$cliente->total}}</th>
                    <th class="bordered destaque" colspan="2"></th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->convenio_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="8" class="top-style fontSize15 nobord">Convênio: {{$cliente->convenio}}</td>
                </tr>
                <!-- {{$first = false}} -->
                <tr>
                    <th></th>
                    <th class="bordered destaque">Cód. Cliente</th>
                    <th class="bordered destaque align-left">Cliente</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th class="bordered destaque align-left">Telefone</th>
                    <th class="bordered destaque">Setor</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->cliente_id))
                <tr>
                    <td></td>
                    <td class="bordered">{{$cliente->cliente_id}}</td>
                    <td class="bordered align-left">{{$cliente->cliente}}</td>
                    <td class="bordered align-left">{{$cliente->endereco}}</td>
                    <td class="bordered align-left">{{$cliente->telefone}}</td>
                    <td class="bordered">{{$cliente->setor}}</td>
                    <td></td>
                </tr>
            @endif
            @if($loop->last)
                <tr>
                    <td colspan="4"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="2">Total Geral</th>
                    <th class="bordered destaque">{{$cliente->totalgeral}}</th>
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