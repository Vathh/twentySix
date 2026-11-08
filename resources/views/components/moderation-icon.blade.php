@props(['name'])

<svg viewBox="0 0 24 24" class="moderation-icon" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    @if($name === 'friend')
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M19 8v6M22 11h-6"/>
    @elseif($name === 'unfriend')
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 11h-6"/>
    @elseif($name === 'flag')
        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
        <path d="M4 22v-7"/>
    @elseif($name === 'follow')
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
        <circle cx="12" cy="12" r="3"/>
    @elseif($name === 'unfollow')
        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
        <path d="M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
        <path d="M2 2l20 20"/>
    @else
        <circle cx="12" cy="12" r="10"/>
        <path d="M4.93 4.93l14.14 14.14"/>
    @endif
</svg>
