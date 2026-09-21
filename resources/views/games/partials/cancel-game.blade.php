@if($canCancelGame ?? false)
    <div
        class="bg-bg-deep rounded-lg p-6 mb-8 border border-danger/40"
        x-data="{ cancelGameOpen: {{ $errors->has('current_password') ? 'true' : 'false' }} }"
    >
        <h2 class="text-lg font-semibold text-danger mb-1">Anuluj mecz</h2>
        <p class="text-text-muted text-sm mb-4">
            Cały przebieg sędziowania zniknie. Mecz wróci na listę oczekujących, jakby nigdy nie wystartował.
        </p>
        <button type="button" class="btn btn-danger" @click="cancelGameOpen = true">Anuluj mecz</button>

        <div
            x-show="cancelGameOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @keydown.escape.window="cancelGameOpen = false"
            @click.self="cancelGameOpen = false"
        >
            <div class="w-full max-w-md rounded-xl border border-danger/40 bg-bg-deep p-6" @click.stop>
                <h2 class="text-lg font-semibold text-danger mb-2">Na pewno anulować mecz?</h2>
                <p class="text-text-muted text-sm mb-4">
                    Legi, wizyty i wynik znikną. Tej operacji nie da się cofnąć.
                </p>
                <form method="POST" action="{{ $cancelAction }}" class="space-y-4">
                    @csrf
                    <label class="block">
                        <span class="form-label">Hasło Twojego konta</span>
                        <input class="input-field" type="password" name="current_password" autocomplete="current-password" required>
                    </label>
                    <x-errors/>
                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <button type="submit" class="btn btn-danger flex-1">Anuluj mecz</button>
                        <button type="button" class="btn btn-secondary flex-1" @click="cancelGameOpen = false">Powrót</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
