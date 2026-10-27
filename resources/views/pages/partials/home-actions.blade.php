<div class="flex flex-col sm:flex-row gap-3 justify-center items-stretch sm:items-center">
    <a href="/tournaments" class="btn btn-primary">Zobacz turnieje</a>
    <a href="{{ route('referee.login') }}" class="btn btn-secondary">Sędziowanie turnieju</a>
</div>
@if(config('mobile.apk_download_url'))
    <div class="mt-3 flex justify-center">
        <a
            href="{{ config('mobile.apk_download_url') }}"
            class="btn btn-secondary"
            target="_blank"
            rel="noopener noreferrer"
        >
            Pobierz aplikację mobilną
        </a>
    </div>
@endif
