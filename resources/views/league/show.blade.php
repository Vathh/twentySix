@extends('layouts.app')

@section('title', $league ? 'Szczegóły' : $league->name)

@section('content')

    <div class="">
        <p class="text-light-orange">{{ $league->name }}</p>
    </div>

@endsection

