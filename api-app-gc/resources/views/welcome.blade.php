@extends('layouts.app')

@section('content')
    <!--{{ $routeName = Route::currentRouteName() }}'-->
    <config page-title="{{$pageTitle}}" v-if="'{!! $routeName !!}' === 'configuser'"></config>
@endsection