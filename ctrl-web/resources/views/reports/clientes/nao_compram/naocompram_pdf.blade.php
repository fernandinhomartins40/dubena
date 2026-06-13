@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize13">
<!-- {{$first = true}} -->
@if(isset($clientes) && count($clientes) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($clientes as $cliente)
            @if(isset($cliente->totalset))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="6">Total</th>
                    <th class="bordered destaque">{{$cliente->totalset}}</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->totalemp))
                <tr>
                    <td colspan="8"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="6">Total Empresa</th>
                    <th class="bordered destaque">{{$cliente->totalemp}}</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->empresa_id))
                 @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8"><br /><hr /></td>
                    </tr>
                @endif
                <!-- {{$first = false}} -->
                <tr>
                    <td colspan="3" class="fontSize15 negrito">{{$cliente->empresa}}</td>
                </tr>
            @elseif(isset($cliente->setor_id))
                <tr>
                    <td colspan="3" class="fontSize14 pad-top-bot-5">&nbsp;&nbsp;{{$cliente->setor}}</td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque">Cód Cliente</th>
                    <th class="bordered destaque align-left">Cliente</th>
                    <th class="bordered destaque align-left">Telefone</th>
                    <th class="bordered destaque">Últ. Compra</th>
                    <th class="bordered destaque">Dias s/ compra</th>
                    <th class="bordered destaque align-left">Bairro</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->cliente_id))
                <tr>
                    <td></td>
                    <td class="bordered">{{$cliente->cliente_id}}</td>
                    <td class="bordered align-left">{{$cliente->nome}}</td>
                    <td class="bordered align-left">{{$cliente->telefone}}</td>
                    <td class="bordered">{{requestDataOracle($cliente->ultcompra,false)}}</td>
                    <td class="bordered">{{$cliente->diff}}</td>
                    <td class="bordered align-left">{{$cliente->bairro}}</td>
                    <td class="bordered align-left">{{$cliente->endereco}}</td>
                    <td></td>
                </tr>
            @endif
            @if ($loop->last)
                <tr>
                    <td colspan="8"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="6">Total Geral</th>
                    <th class="bordered destaque">{{$cliente->total}}</th>
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