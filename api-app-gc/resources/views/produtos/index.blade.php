@extends('layouts.app')

@section('content')

<products products-server="{{ $products }}" page-title="{{ $pageTitle }}"></products>

@endsection