
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Cliente/Fornecedor
                            </h3>
                        </div>
                    </div><!-- /.box-header -->
                    @include('clientes.form_clientes')
                </div>
            </ul><!-- /.col -->
        </div>
    </div>
</div>

@endsection
