@extends('layouts.app')

@section('title', 'Strona główna')

@section('content')

    <div class="flex items-center justify-center w-full min-h-[70vh]">
        <div class="text-center py-12 bg-lighter-bg rounded-lg mb-8 shadow-lg px-15">
            <h2 class="text-4xl font-bold mb-4 text-light-green">Witamy w DartScore!</h2>
            <p class="text-lg mb-10 text-light-white">Śledź turnieje, rankingi i wyniki na żywo.</p>
            <a href="/tournaments" class="btn btn-primary">Zobacz turnieje</a>
        </div>
    </div>

@endsection
