@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
    @if(isset($classes) && count($classes) > 0)
        @foreach($classes as $classe)
        <p class="fontSize15 negrito">Tipo de Veículo: {{$classe->classe}}</p>
        <table id="tblCadastro" class="table500" width="65%">
            <thead>
                <tr class="bordered destaque">
                    <th class="bordered">Veículo</th>
                    <th class="bordered">Motorista</th>
                    <th class="bordered">Última Troca</th>
                    <th class="bordered">Km Atual</th>
                    <th class="bordered">Próxima Troca</th>
                </tr>
            </thead>
            <tbody id="repor-list" name="report-list">
                @foreach($vencidos as $oleo)
                    @if($oleo->classe_id == $classe->classe_id)
                        <tr class="bordered">
                            <td class="bordered" width="20%">{{$oleo->placa}} - {{$oleo->descricao}}</td>
                            <td class="bordered" width="25%">{{$oleo->motorista}}</td>
                            <td class="bordered" width="15%">{{$oleo->ultimatroca}}</td>
                            <td class="bordered" width="15%">{{$oleo->kmatual}}</td>
                            <td class="bordered" width="15%">{{$oleo->proximatroca}}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        <br />
        <hr/>
        <br />
        @endforeach
    @else
        <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
    @endif
</div>

@endsection