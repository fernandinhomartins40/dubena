@extends('layouts.reportNoBootstrap')

@section('content')

<div class="fontSize12">

    @if (isset($ausentes) && count($ausentes) > 0)

        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">

            <thead>
                <tr>
                    <th class="bordered destaque">Promotor</th>
                    <th class="bordered destaque">Visitado Em</th>
                    <th class="bordered destaque">Cidade</th>
                    <th class="bordered destaque">Bairro</th>
                    <th class="bordered destaque">Rua</th>
                    <th class="bordered destaque">Número</th>
                    <th class="bordered destaque">Setor</th>
                    <th class="bordered destaque">Complemento</th>
                    <th class="bordered destaque">Referência</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ausentes as $ausente)
                    <tr>
                        <td class="bordered">{{ $ausente->responsa }}</td>
                        <td class="bordered">{{ requestDataOracle($ausente->visitado_em, false) }}</td>
                        <td class="bordered">{{ $ausente->cidade }}</td>
                        <td class="bordered">{{ $ausente->bairro }}</td>
                        <td class="bordered">{{ $ausente->rua }}</td>
                        <td class="bordered">{{ $ausente->numero }}</td>
                        <td class="bordered">{{ $ausente->setor }}</td>
                        <td class="bordered">{{ $ausente->complemento }}</td>
                        <td class="bordered">{{ $ausente->ponto_referencia }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th class="bordered destaque">Total</th>
                    <th class="bordered destaque align-right" colspan="8">{{ $total }}</th>
                </tr>
            </tbody>

        </table>

    @else

        <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>

    @endif

</div>

@endsection
