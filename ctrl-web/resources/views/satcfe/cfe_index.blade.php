@extends('layouts.mainmenu')
@section('content')
    <div id="mainContent" class="content">
        <div id="divCadastro">
            <div class="row">
                <div class="col-xs-12">
                    <div class="box-header">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-6" style="margin-bottom:1%">
                                    @can('create', App\CupomFiscal::class)
                                        <a id="btnNovo" class="btn btn-nw-registro" href="{{ $fullUrl }}">Novo Registro</a>
                                    @endcan
                                </div> <!--col-md-6-->
                            </div> <!--col-md-12-->
                        </div><!--row-->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="box-title">Cupons Fiscais</h3>
                            </div><!-- /.box-header -->
                            <div class="panel-body">
                                <div class="col-md-12">
                                    @include('satcfe.partials.cfe_table')
                                    <div id='rotaDel' class="hidden">{{url('nfrecebida')}}/</div>
                                    <div id='rotaIndex' class="hidden">{{$fullUrl}}</div>
                                </div>
                            </div><!-- /.box-body -->
                        </div><!-- /.box -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-5">
                                    @can('create', App\CupomFiscal::class)
                                        <a id="btnNovo" class="btn btn-nw-registro" href="{{ $fullUrl }}">Novo Registro</a>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div><!-- /.col -->
                </div><!-- /.row -->
                <!-- page script -->
            </div><!-- /.content-wrapper -->
        </div>
    </div>
@endsection
