@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
    @if(isset($oleoveiculo) && count($oleoveiculo)>0)
        @foreach($oleoveiculo as $troca)
        <p class="fontSize15 negrito">Veículo: {{$troca->veiculo}}</p>
            <table id="tblCadastro" class="table500">
                <thead>
                    <tr class="bordered destaque">
                        <th class="bordered">Data</th>
                        <th class="bordered">Condutor</th>
                        <th class="bordered">Km Última Troca</th>
                        <th class="bordered">Próxima Troca</th>
                    </tr>
                </thead>
                <tbody id="repor-list" name="report-list">
                    @foreach($trocaoleo as $oleo)
                        @if($troca->veiculo_id == $oleo->veiculo_id)
                            <tr class="bordered">
                                <td class="bordered">{{$oleo->datatroca}}</td>
                                <td class="bordered">{{$oleo->condutor}}</td>
                                <td class="bordered">{{$oleo->kmtroca}}</td>
                                <td class="bordered">{{$oleo->proximatroca}}</td>
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