@extends('layouts.app')

@section('title', $definition->name.' — trening')

@section('content')
@php
    $kind = $definition->kind;
    $passwordForStatus = $kind === \App\Domain\Training\TrainingKind::Event;
    $canCancel = in_array($kind, [
        \App\Domain\Training\TrainingKind::Plan,
        \App\Domain\Training\TrainingKind::Challenge,
        \App\Domain\Training\TrainingKind::Event,
    ], true);
    $canRemove = $kind === \App\Domain\Training\TrainingKind::Event;
@endphp
<div class="max-w-6xl mx-auto px-4 pt-6 pb-12" x-data="{ dialog: @js(old('dialog')) }">
    <a href="{{ route('admin.training.list', $kind->slug()) }}" class="text-text-secondary text-sm hover:text-accent">← {{ $kind->plural() }}</a>

    <div class="flex items-start gap-4 mt-3 mb-6">
        <x-player-avatar :name="$definition->name" :avatar-url="$definition->imageUrl()" size="lg" />
        <div>
            <h1 class="text-xl sm:text-2xl font-semibold text-accent">{{ $definition->name }}</h1>
            <p class="text-text-secondary text-sm mt-1">{{ $kind->singular() }} · {{ $definition->status->label() }}</p>
        </div>
    </div>

    <div class="card p-5 space-y-4 mb-6">
        @if($definition->description)
            <p class="text-text whitespace-pre-line">{{ $definition->description }}</p>
        @else
            <p class="text-text-muted text-sm">Bez opisu.</p>
        @endif
        <p class="text-sm text-text-secondary">
            @include('admin.training.partials.summary', ['definition' => $definition])
        </p>
        @if($definition->isPublished())
            <p class="text-text-muted text-xs">Opublikowane {{ $definition->published_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}. Tej treści już nie edytujemy.</p>
        @else
            <p class="text-text-muted text-xs">Jeszcze nieopublikowane. Można poprawić treść przed włączeniem.</p>
        @endif

        @if($definition->items->isNotEmpty())
            <ol class="space-y-2 text-sm">
                @foreach($definition->items as $item)
                    <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                        <span class="text-text">
                            {{ $item->position }}.
                            @if($item->item_kind === \App\Domain\Training\TrainingItemKind::Break)
                                Przerwa
                            @else
                                {{ $item->exerciseLabel() }}
                            @endif
                        </span>
                        <span class="text-text-secondary">
                            @if($item->item_kind === \App\Domain\Training\TrainingItemKind::Break)
                                {{ $item->break_seconds }} s
                            @else
                                @if($item->duration_seconds)
                                    {{ $item->duration_seconds }} s
                                @elseif($item->dart_count)
                                    {{ $item->dart_count }} lotek
                                @endif
                                @if($item->variant)
                                    · {{ $item->variant }}
                                @endif
                                · {{ $item->scored ? 'punktowane' : 'rozgrzewka' }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    @if(!empty($stats))
        @include('admin.training.partials.stats', ['definition' => $definition, 'stats' => $stats])
    @endif

    <div class="flex flex-wrap gap-2">
        @if(! $definition->isPublished())
            <a href="{{ route('admin.training.edit', $definition) }}" class="btn btn-secondary">Edytuj</a>
        @endif
        @if($definition->status === \App\Domain\Training\TrainingDefinitionStatus::Inactive)
            <button type="button" class="btn btn-primary" @click="dialog = 'activate'">Aktywuj</button>
        @endif
        @if($definition->status === \App\Domain\Training\TrainingDefinitionStatus::Active && $kind !== \App\Domain\Training\TrainingKind::Event)
            <button type="button" class="btn btn-secondary" @click="dialog = 'deactivate'">Dezaktywuj</button>
        @endif
        @if($kind === \App\Domain\Training\TrainingKind::Event && $definition->status === \App\Domain\Training\TrainingDefinitionStatus::Active)
            <button type="button" class="btn btn-secondary" @click="dialog = 'pause'">Wstrzymaj</button>
            <button type="button" class="btn btn-secondary" @click="dialog = 'close'">Zakończ wcześniej</button>
        @endif
        @if($kind === \App\Domain\Training\TrainingKind::Event && $definition->status === \App\Domain\Training\TrainingDefinitionStatus::Paused)
            <button type="button" class="btn btn-primary" @click="dialog = 'resume'">Wznów</button>
            <button type="button" class="btn btn-secondary" @click="dialog = 'close'">Zakończ wcześniej</button>
        @endif
        @if($canCancel)
            <button type="button" class="btn btn-danger" @click="dialog = 'cancel'">Anuluj</button>
        @endif
        @if($canRemove)
            <button type="button" class="btn btn-danger" @click="dialog = 'remove'">Usuń</button>
        @endif
    </div>

    @if($definition->status === \App\Domain\Training\TrainingDefinitionStatus::Inactive)
        @include('admin.training.partials.action', [
            'dialog' => 'activate',
            'title' => 'Aktywować?',
            'action' => route('admin.training.activate', $definition),
            'label' => 'Aktywuj',
            'password' => $passwordForStatus,
        ])
    @endif
    @if($definition->status === \App\Domain\Training\TrainingDefinitionStatus::Active && $kind !== \App\Domain\Training\TrainingKind::Event)
        @include('admin.training.partials.action', [
            'dialog' => 'deactivate',
            'title' => 'Dezaktywować?',
            'action' => route('admin.training.deactivate', $definition),
            'label' => 'Dezaktywuj',
            'password' => $passwordForStatus,
        ])
    @endif
    @if($kind === \App\Domain\Training\TrainingKind::Event && $definition->status === \App\Domain\Training\TrainingDefinitionStatus::Active)
        @include('admin.training.partials.action', [
            'dialog' => 'pause',
            'title' => 'Wstrzymać event?',
            'action' => route('admin.training.event.pause', $definition),
            'label' => 'Wstrzymaj',
            'password' => true,
        ])
        @include('admin.training.partials.action', [
            'dialog' => 'close',
            'title' => 'Zakończyć event wcześniej?',
            'action' => route('admin.training.event.close', $definition),
            'label' => 'Zakończ',
            'password' => true,
        ])
    @endif
    @if($kind === \App\Domain\Training\TrainingKind::Event && $definition->status === \App\Domain\Training\TrainingDefinitionStatus::Paused)
        @include('admin.training.partials.action', [
            'dialog' => 'resume',
            'title' => 'Wznowić event?',
            'action' => route('admin.training.event.resume', $definition),
            'label' => 'Wznów',
            'password' => true,
        ])
        @include('admin.training.partials.action', [
            'dialog' => 'close',
            'title' => 'Zakończyć event wcześniej?',
            'action' => route('admin.training.event.close', $definition),
            'label' => 'Zakończ',
            'password' => true,
        ])
    @endif
    @if($canCancel)
        @include('admin.training.partials.action', [
            'dialog' => 'cancel',
            'title' => 'Anulować?',
            'action' => route('admin.training.cancel', $definition),
            'label' => 'Anuluj',
            'danger' => true,
            'password' => true,
        ])
    @endif
    @if($canRemove)
        @include('admin.training.partials.action', [
            'dialog' => 'remove',
            'title' => 'Usunąć?',
            'action' => route('admin.training.remove', $definition),
            'label' => 'Usuń',
            'danger' => true,
            'password' => true,
        ])
    @endif
</div>
@endsection
