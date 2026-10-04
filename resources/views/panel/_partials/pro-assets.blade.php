@once

    @push('scripts')
        <script src="{{ asset('dashboard/assets/js/mecacuy/pro-views.js') }}?v={{ filemtime(public_path('dashboard/assets/js/mecacuy/pro-views.js')) }}"></script>
    @endpush
@endonce
