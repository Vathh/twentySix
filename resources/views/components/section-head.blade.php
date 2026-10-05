@props(['title'])

<div {{ $attributes->class(['section-head']) }}>
    <h2 class="section-title">{{ $title }}</h2>
    @isset($action)
        @if(! $action->isEmpty())
            <div class="section-head-action">{{ $action }}</div>
        @endif
    @endisset
</div>
