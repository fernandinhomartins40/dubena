@extends('layouts.app')

@section('content')
    <general-config page-title="{{$pageTitle}}" data-model="{{ $config }}"></general-config>
@endsection