@extends('layouts.reportNoBootstrap')

@section('content')

<div class="fontSize12">

    @if (isset($visitados) && count($visitados) > 0)

        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">

            <thead>
                <tr>
                    <th class="bordered destaque">Cliente</th>
                    <th class="bordered destaque">Visitado Em</th>
                    <th class="bordered destaque">Cidade</th>
                    <th class="bordered destaque">Bairro</th>
                    <th class="bordered destaque">Rua</th>
                    <th class="bordered destaque">Número</th>
                    <th class="bordered destaque">Setor</th>
                    <th class="bordered destaque">Produto</th>
                    <th class="bordered destaque">Tipo</th>
                    <th class="bordered destaque">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($visitados as $visitado)
                    <tr>
                        <td class="bordered align-left">{{ $visitado->cliente }}</td>
                        <td class="bordered">{{ $visitado->visitado_em }}</td>
                        <td class="bordered">{{ $visitado->cidade }}</td>
                        <td class="bordered">{{ $visitado->bairro }}</td>
                        <td class="bordered">{{ $visitado->rua }}</td>
                        <td class="bordered">{{ $visitado->numero }}</td>
                        <td class="bordered">{{ $visitado->setor }}</td>
                        <td class="bordered">{{ $visitado->produto }}</td>
                        <td class="bordered">{{ $visitado->tipo_negociado }}</td>
                        <td class="bordered">{{ $visitado->valor_negociado }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th class="bordered destaque">Total</th>
                    <th class="bordered destaque align-right" colspan="9">{{ $total }}</th>
                </tr>
            </tbody>

        </table>

    @else

        <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>

    @endif

</div>

@endsection
