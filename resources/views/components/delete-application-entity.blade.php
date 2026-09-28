@props([
    'action',
    'name',
    'label',
    'hint',
])

<button
    type="button"
    class="admin-sidebar-link w-full text-left text-danger"
    @click="$dispatch('open-entity-delete')"
>
    Usuń {{ $label }}
</button>

<div
    x-data="{ open: {{ ($errors->has('delete_password') || $errors->has('entity_name_confirmation')) ? 'true' : 'false' }} }"
    @open-entity-delete.window="open = true"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    @keydown.escape.window="open = false"
    @click.self="open = false"
>
    <div class="w-full max-w-md rounded-xl border border-danger/40 bg-bg-deep p-6" @click.stop>
        <h2 class="text-lg font-semibold text-danger mb-2">Na pewno usunąć {{ $label }}?</h2>
        <p class="text-text-muted text-sm mb-4">{{ $hint }}</p>
        <form method="POST" action="{{ $action }}" class="space-y-4">
            @csrf
            @method('DELETE')
            <label class="block">
                <span class="form-label">Wpisz nazwę: {{ $name }}</span>
                <input class="input-field" type="text" name="entity_name_confirmation" autocomplete="off" required>
            </label>
            <label class="block">
                <span class="form-label">Hasło Twojego konta</span>
                <input class="input-field" type="password" name="delete_password" autocomplete="current-password" required>
            </label>
            <x-errors/>
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <button type="submit" class="btn btn-danger flex-1">Usuń</button>
                <button type="button" class="btn btn-secondary flex-1" @click="open = false">Powrót</button>
            </div>
        </form>
    </div>
</div>
