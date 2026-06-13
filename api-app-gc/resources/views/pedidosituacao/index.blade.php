@extends('layouts.app')

@section('content')

    <pedido-situacao page-title="{{ $pageTitle }}" status-server="{{ $status }}" status-def-server="{{ $statusDefault }}"></pedido-situacao>

@endsection