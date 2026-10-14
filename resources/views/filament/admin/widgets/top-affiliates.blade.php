<x-filament-widgets::widget>
    <x-filament::section :heading="__('admin_reports.top_affiliates')">
        @forelse ($affiliates as $affiliate)
            <div class="flex items-center justify-between gap-4 py-2">
                <span>{{ $affiliate['name'] }}</span>
                <span>{{ \App\Services\DisplayFormatter::rupiah($affiliate['total_paid']) }} · {{ __('admin_reports.sales_quantity', ['count' => $affiliate['sales_count']]) }}</span>
            </div>
        @empty
            <p>{{ __('admin_reports.no_sales') }}</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
