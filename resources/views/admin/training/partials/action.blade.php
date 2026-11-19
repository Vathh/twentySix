@props([
    'dialog',
    'title',
    'action',
    'label',
    'danger' => false,
    'password' => false,
])

<div
    x-show="dialog === '{{ $dialog }}'"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    @keydown.escape.window="if (dialog === '{{ $dialog }}') dialog = null"
    @click.self="dialog = null"
>
    <div class="w-full max-w-md rounded-xl border {{ $danger ? 'border-danger/40' : 'border-border' }} bg-bg-deep p-6" @click.stop>
        <h2 class="text-lg font-semibold {{ $danger ? 'text-danger' : 'text-text' }} mb-4">{{ $title }}</h2>
        <form method="POST" action="{{ $action }}" class="space-y-4">
            @csrf
            <input type="hidden" name="dialog" value="{{ $dialog }}">
            @if($password)
                <label class="block">
                    <span class="form-label">Hasło Twojego konta</span>
                    <input class="input-field" type="password" name="current_password" autocomplete="current-password" required>
                </label>
                <label class="flex items-center gap-2 text-sm text-text">
                    <input type="checkbox" name="acknowledge" value="1" required>
                    <span>Potwierdzam</span>
                </label>
            @endif
            <x-errors/>
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <button type="submit" class="btn {{ $danger ? 'btn-danger' : 'btn-primary' }} flex-1">{{ $label }}</button>
                <button type="button" class="btn btn-secondary flex-1" @click="dialog = null">Powrót</button>
            </div>
        </form>
    </div>
</div>
