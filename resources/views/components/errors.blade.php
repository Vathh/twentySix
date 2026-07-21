@if($errors->any())
    <ul class="alert-danger mt-6 space-y-1">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
