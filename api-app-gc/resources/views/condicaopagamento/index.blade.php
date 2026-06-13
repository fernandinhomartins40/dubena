@extends('layouts.app')

@section('content')

<payway page-title="{{ $pageTitle }}" payways-server="{{ $payways }}" types-server="{{ $paywayType }}"></payway>

@endsection