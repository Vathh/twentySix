@extends('layouts.app')

@section('title', 'Edycja profilu')

@section('content')
    <div class="flex justify-center items-start min-h-[70vh] px-4 py-5">
        <form class="form-card w-full max-w-3xl"
              action="{{ route('players.update', $player) }}"
              method="POST"
              enctype="multipart/form-data"
              x-data="playerAvatarCrop(@js([
                  'avatarUrl' => $player->avatarUrl(),
                  'initials' => $player->initials(),
              ]))">
            @csrf
            @method('PUT')
            <input type="hidden" name="remove_avatar" :value="removed ? '1' : '0'">
            <input type="file" name="avatar" class="hidden" x-ref="avatarInput" tabindex="-1" aria-hidden="true">
            <input type="file"
                   class="hidden"
                   x-ref="picker"
                   accept="image/jpeg,image/png,image/webp"
                   @change="pick($event)">

            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Edycja profilu</h1>
                <p class="text-text-secondary text-center mb-6">{{ $player->name }}</p>

                <div class="flex flex-col items-center gap-3 mb-6">
                    <template x-if="!cropping">
                        <div class="flex flex-col items-center gap-3">
                            <template x-if="showImage">
                                <img class="player-avatar player-avatar--lg" :src="previewUrl" alt="" x-on:error="previewUrl = ''">
                            </template>
                            <template x-if="!showImage">
                                <span class="player-avatar player-avatar--lg">
                                    <span class="player-avatar-fallback" x-text="initials"></span>
                                </span>
                            </template>
                            <div class="flex flex-wrap justify-center gap-2">
                                <button class="btn btn-mini" type="button" @click="openPicker()">Wybierz zdjęcie</button>
                                <button class="btn btn-mini" type="button" x-show="showImage" x-cloak @click="remove()">Usuń zdjęcie</button>
                            </div>
                            <p class="text-text-muted text-xs text-center">JPEG, PNG lub WebP, do 2 MB. Po wyborze przytniesz kadr do kwadratu.</p>
                        </div>
                    </template>

                    <template x-if="cropping">
                        <div class="w-full">
                            <p class="text-text-secondary text-sm text-center mb-3">Przesuń zdjęcie i ustaw kwadratowy kadr.</p>
                            <div class="avatar-crop-frame overflow-hidden rounded-lg border border-border">
                                <img x-ref="cropImage" :src="sourceUrl" alt="" @load="startCropper()">
                            </div>
                            <div class="flex flex-wrap justify-center gap-2 mt-3">
                                <button class="btn btn-mini" type="button" @click="zoom(-0.1)">Oddal</button>
                                <button class="btn btn-mini" type="button" @click="zoom(0.1)">Przybliż</button>
                                <button class="btn btn-primary" type="button" @click="confirmCrop()">Przytnij</button>
                                <button class="btn btn-mini" type="button" @click="cancelCrop()">Anuluj</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-data="{ count: {{ strlen(old('description', $player->description ?? '')) }} }">
                    <label class="form-label text-accent" for="description">Opis</label>
                    <textarea
                        class="input-field mb-2 h-40 resize-y"
                        id="description"
                        name="description"
                        maxlength="1000"
                        placeholder="Napisz coś o sobie…"
                        x-on:input="count = $el.value.length"
                    >{{ old('description', $player->description) }}</textarea>

                    <div class="text-accent text-sm text-right mb-6">
                        <span x-text="count">{{ strlen(old('description', $player->description ?? '')) }}</span>/1000
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 justify-center">
                    <button class="btn btn-primary" type="submit" :disabled="cropping">Zapisz</button>
                    <a href="{{ route('players.show', $player) }}" class="btn btn-mini border border-border text-text-secondary bg-transparent hover:bg-bg-elevated">Anuluj</a>
                </div>
                <p class="text-text-muted text-xs text-center mt-3" x-show="cropping" x-cloak>Najpierw zatwierdź kadr albo anuluj.</p>

                <x-errors/>
            </div>
        </form>
    </div>
@endsection
