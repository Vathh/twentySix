@props(['value' => null])
@php
    $text = \App\Support\AverageFormat::display($value, '');
@endphp
@if($text !== '')
    <span {{ $attributes->merge(['class' => 'three-dart-average']) }}>{{ $text }}</span>
@endif
