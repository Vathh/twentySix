@props([
    'addAction',
    'removeAction',
    'guests' => [],
    'empty' => 'W tej puli nie ma jeszcze gości.',
])

<form action="{{ $addAction }}" method="POST" class="people-add">
    @csrf
    <input
        type="text"
        name="name"
        value="{{ old('name') }}"
        placeholder="Imię gościa"
        maxlength="20"
        required
        class="input-field"
        autocomplete="off"
    >
    <button type="submit" class="btn btn-primary">Dodaj</button>
</form>

<x-errors/>

@php
    $guestItems = collect($guests)->map(fn ($guest) => [
        'id' => $guest['id'],
        'name' => $guest['name'],
    ])->all();
    $polishRank = array_flip(['a', 'ą', 'b', 'c', 'ć', 'd', 'e', 'ę', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'ł', 'm', 'n', 'ń', 'o', 'ó', 'p', 'q', 'r', 's', 'ś', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'ź', 'ż']);
    $polishKey = function (string $name) use ($polishRank): string {
        $name = mb_strtolower($name, 'UTF-8');
        $key = '';
        $length = mb_strlen($name, 'UTF-8');
        for ($index = 0; $index < $length; $index++) {
            $char = mb_substr($name, $index, 1, 'UTF-8');
            $key .= isset($polishRank[$char]) ? sprintf('a%02d', $polishRank[$char]) : 'z'.$char;
        }

        return $key;
    };
    usort($guestItems, fn (array $left, array $right): int => $polishKey($left['name']) <=> $polishKey($right['name']));
    $guestGroups = [];
    foreach ($guestItems as $guestItem) {
        $trimmedName = ltrim($guestItem['name']);
        $letter = $trimmedName === '' ? '#' : mb_strtoupper(mb_substr($trimmedName, 0, 1), 'UTF-8');
        if ($guestGroups === [] || $guestGroups[array_key_last($guestGroups)]['letter'] !== $letter) {
            $guestGroups[] = ['letter' => $letter, 'people' => []];
        }
        $guestGroups[array_key_last($guestGroups)]['people'][] = $guestItem;
    }
@endphp

@if(count($guestItems) === 0)
    <x-empty-state
        class="!py-10"
        title="Brak gości"
        :description="$empty"
    />
@else
    <div class="people-catalog">
        @foreach($guestGroups as $group)
            <section class="people-letter">
                <p class="people-letter-label">{{ $group['letter'] }}</p>
                <div class="people-list">
                    @foreach($group['people'] as $guest)
                        <div class="people-row">
                            <span class="people-row-name">{{ $guest['name'] }}</span>
                            <form action="{{ $removeAction }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="player_id" value="{{ $guest['id'] }}">
                                <button type="submit" class="people-x" aria-label="Usuń">
                                    <svg class="people-x-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                        <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
