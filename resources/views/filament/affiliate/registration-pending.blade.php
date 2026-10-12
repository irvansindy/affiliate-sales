@if (session('registration_pending'))
    <x-filament::section>
        <p role="status">{{ __('affiliates.registration_pending') }}</p>
    </x-filament::section>
@endif
