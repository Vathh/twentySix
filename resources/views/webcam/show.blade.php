@extends('layouts.app')

@section('title', 'Mecz kamerkowy')

@section('content')
    <style>[x-cloak]{display:none !important}</style>
    <div
        class="flex flex-col gap-4"
        x-data="webcamMatch(@js(array_merge($config, ['state' => $state])))"
    >
        <div class="flex items-baseline justify-between gap-3">
            <h1 class="text-2xl font-semibold">
                <span x-text="me?.name"></span>
                <span class="text-text-muted font-normal">vs</span>
                <span x-text="opponent?.name"></span>
            </h1>
            <a href="{{ route('webcam.index') }}" class="text-sm text-text-muted no-underline hover:text-accent">Lista meczów</a>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="flex flex-col gap-3 min-w-0">
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-text-muted">Kamerka na tarczę</span>
                        <select class="rounded-lg border border-border bg-transparent px-3 py-2" x-model="boardDeviceId" @change="onDeviceChange()">
                            <template x-for="device in devices" :key="device.id">
                                <option :value="device.id" x-text="device.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-text-muted">Kamerka na ciebie</span>
                        <select class="rounded-lg border border-border bg-transparent px-3 py-2" x-model="faceDeviceId" @change="onDeviceChange()">
                            <template x-for="device in devices" :key="'face-' + device.id">
                                <option :value="device.id" x-text="device.label"></option>
                            </template>
                        </select>
                    </label>
                </div>
                <p class="text-sm text-amber-300" x-show="camError" x-text="camError"></p>

                <div class="relative rounded-xl overflow-hidden bg-black aspect-video">
                    <video x-ref="remoteBoard" class="w-full h-full object-cover" autoplay playsinline muted></video>
                    <video x-ref="remoteFace" class="absolute bottom-3 right-3 w-28 sm:w-36 aspect-video object-cover rounded-lg border border-white/20 bg-black" autoplay playsinline muted></video>
                    <p class="absolute left-3 top-3 text-sm text-white/80" x-text="(opponent?.name || 'Przeciwnik') + ' · tarcza'"></p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-xs text-text-muted mb-1">Twoja tarcza</p>
                        <video x-ref="localBoard" class="w-full aspect-video object-cover rounded-lg bg-black" autoplay playsinline muted></video>
                    </div>
                    <div>
                        <p class="text-xs text-text-muted mb-1">Ty</p>
                        <video x-ref="localFace" class="w-full aspect-video object-cover rounded-lg bg-black" autoplay playsinline muted></video>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary self-start" @click="reconnectVideo()">Połącz obraz ponownie</button>
            </section>

            <aside class="rounded-xl border border-border p-4 flex flex-col gap-4">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-sm text-text-muted">Leg <span x-text="state.legNumber"></span> · do <span x-text="state.legsToWin"></span></p>
                        <p class="text-3xl font-semibold tabular-nums">
                            <span x-text="me?.legsWon ?? 0"></span>
                            <span class="text-text-muted">:</span>
                            <span x-text="opponent?.legsWon ?? 0"></span>
                        </p>
                    </div>
                    <p class="text-sm text-text-muted" x-show="state.status === 'live'" x-text="myTurn ? 'Twoja tura' : 'Tura przeciwnika'"></p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-border p-3" :class="myTurn && 'border-accent'">
                        <p class="text-sm text-text-muted" x-text="me?.name"></p>
                        <p class="text-4xl font-semibold tabular-nums" x-text="me?.remaining ?? state.startingScore"></p>
                    </div>
                    <div class="rounded-lg border border-border p-3">
                        <p class="text-sm text-text-muted" x-text="opponent?.name"></p>
                        <p class="text-4xl font-semibold tabular-nums" x-text="opponent?.remaining ?? state.startingScore"></p>
                    </div>
                </div>

                <p class="text-sm" x-show="state.status === 'lobby'">
                    <span x-show="me?.ready">Jesteś gotowy. </span>
                    <span x-show="!me?.ready">Oznacz się jako gotowy, gdy kamerki stoją. </span>
                    <span x-text="opponent?.ready ? 'Przeciwnik jest gotowy.' : 'Czekam na przeciwnika.'"></span>
                </p>
                <button type="button" class="btn btn-primary" x-show="state.status === 'lobby' && !me?.ready" :disabled="busy" @click="markReady()">Gotowy</button>

                <p class="font-medium" x-show="state.status === 'finished'">
                    Koniec. Wygrywa <span x-text="playerName(state.winnerUserId)"></span>.
                </p>

                <form class="flex flex-col gap-2" x-show="myTurn" @submit.prevent="submitInput()">
                    <label class="text-sm text-text-muted" for="webcam-score">Suma wizyty</label>
                    <input id="webcam-score" inputmode="numeric" class="rounded-lg border border-border bg-transparent px-3 py-2 text-lg tabular-nums" x-model="input" autocomplete="off">
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary" :disabled="busy">Zapisz</button>
                        <button type="button" class="btn btn-secondary" :disabled="busy" @click="bustOpen = true">Bust</button>
                    </div>
                </form>
                <p class="text-sm text-red-400" x-show="error" x-text="error"></p>
                <button type="button" class="btn btn-secondary self-start" x-show="canUndo" :disabled="busy" @click="undo()">Cofnij ostatnią wizytę</button>

                <ul class="flex flex-col gap-1 text-sm">
                    <template x-for="visit in legVisits" :key="visit.id">
                        <li class="flex justify-between gap-3 tabular-nums">
                            <span x-text="playerName(visit.userId)"></span>
                            <span x-text="visitLabel(visit)"></span>
                        </li>
                    </template>
                </ul>

                <div x-show="checkoutOpen" x-cloak class="rounded-lg border border-border p-3 flex flex-col gap-2">
                    <p class="font-medium">Checkout?</p>
                    <div class="flex gap-2">
                        <template x-for="darts in [1, 2, 3]" :key="darts">
                            <button type="button" class="btn btn-secondary" :class="checkoutDarts === darts && 'border-accent'" @click="checkoutDarts = darts" x-text="darts + (darts === 1 ? ' lotka' : ' lotki')"></button>
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-primary" @click="confirmCheckout()">Zamknij lega</button>
                        <button type="button" class="btn btn-secondary" @click="checkoutOpen = false">Anuluj</button>
                    </div>
                </div>

                <div x-show="bustOpen" x-cloak class="rounded-lg border border-border p-3 flex flex-col gap-2">
                    <p class="font-medium">Bust — wynik wizyty się nie liczy.</p>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-primary" @click="confirmBust()">Potwierdź bust</button>
                        <button type="button" class="btn btn-secondary" @click="bustOpen = false">Anuluj</button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection
