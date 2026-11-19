@php
    $bits = [];
    if ($definition->difficulty) {
        $bits[] = $definition->difficulty->label();
    }
    if ($definition->declared_minutes) {
        $bits[] = $definition->declared_minutes.' min';
    }
    if ($definition->kind === \App\Domain\Training\TrainingKind::Challenge && is_string($definition->mechanic) && $definition->mechanic !== '') {
        $bits[] = \App\Domain\Training\TrainingChallengeCatalog::label($definition->mechanic);
    }
    if ($definition->duration_seconds) {
        $bits[] = $definition->duration_seconds.' s';
    }
    if ($definition->dart_count) {
        $bits[] = $definition->dart_count.' lotek';
    }
@endphp
{{ $bits === [] ? '—' : implode(' · ', $bits) }}
