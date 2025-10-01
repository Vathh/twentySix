@extends('layouts.app')

@section('title', $league ? 'Szczegóły' : $league->name)

@section('content')

    <div class="">
        <p>test {{ $league->name }}</p>
    </div>

@endsection

