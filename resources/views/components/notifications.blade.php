@php
    $alerts = [];
    if(session('success')) {
        $alerts[] = ['type' => 'success', 'text' => session('success')];
    }
    if(session('error')) {
        $alerts[] = ['type' => 'error', 'text' => session('error')];
    }
@endphp

<x-alert :messages="$alerts" duration="4000" />
