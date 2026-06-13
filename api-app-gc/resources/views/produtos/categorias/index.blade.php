@extends('layouts.app')

@section('content')

<produtos-categorias categories="{{ @$categories }}"></produtos-categorias>

@endsection