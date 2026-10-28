@if(config('mobile.apk_download_url'))
    <div class="mb-4 flex justify-center">
        <a
            href="{{ config('mobile.apk_download_url') }}"
            class="btn btn-secondary"
            target="_blank"
            rel="noopener noreferrer"
        >Pobierz aplikację mobilną</a>
    </div>
@endif
