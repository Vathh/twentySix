@extends('layouts.app')

@section('title', 'Sezony')

@section('content')

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-dark-bg shadow rounded-lg p-6 hover:shadow-xl hover:cursor-pointer transition">
            <h3 class="text-xl font-semibold mb-2 text-light-orange">Wiosna 2026</h3>
            <p class="mb-2">Data: 05-10-2025</p>
            <p class="mb-4">Zawodnicy: 20</p>
            <a href="#" class="text-light-green hover:underline font-semibold transition">Szczegóły</a>
        </div>
    </div>

@endsection

