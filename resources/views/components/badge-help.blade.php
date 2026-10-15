@props(['text' => ''])

@if($text !== '')
    <div class="badge-help" @keydown.escape.window="help = false">
        <button
            type="button"
            class="badge-help__btn"
            @click.stop="help = !help; if (help) $dispatch('badge-help-open', $root)"
            :aria-expanded="help ? 'true' : 'false'"
            aria-label="Co oznacza ta odznaka"
        >?</button>
        <p
            class="badge-help__bubble"
            x-show="help"
            x-cloak
            @click.stop
            @click.outside="help = false"
            role="tooltip"
        >{{ $text }}</p>
    </div>
@endif
