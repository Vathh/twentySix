@props([
    'title',
    'kind',
    'tone' => 'organization',
    'organizationName' => null,
    'organizationUrl' => null,
    'seasonName' => null,
    'seasonUrl' => null,
    'leagueName' => null,
    'leagueUrl' => null,
    'lead' => null,
])

<div class="detail-layout">
    <div class="detail-main">
        <div class="detail-content">
            <x-place-bar
                current="organization"
                :organization-name="$organizationName"
                :organization-url="$organizationUrl"
                :season-name="$seasonName"
                :season-url="$seasonUrl"
                :league-name="$leagueName"
                :league-url="$leagueUrl"
                :people-title="$title"
                :people-kind="$kind"
                :people-tone="$tone"
            />

            @if(filled($lead))
                <p class="place-lead -mt-4">{{ $lead }}</p>
            @endif

            {{ $slot }}
        </div>
    </div>
</div>
