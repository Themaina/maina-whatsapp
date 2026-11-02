<div class="row g-2 p-3">
    @foreach (['sent' => 'info', 'delivered' => 'primary', 'read' => 'success', 'failed' => 'danger', 'received' => 'warning', 'queued' => 'secondary'] as $key => $color)
        <div class="col-6 col-lg-4">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-muted small">{{ trans('plugins/whatsapp::whatsapp.widget.' . $key) }}</div>
                    <div class="h2 mb-0 text-{{ $color }}">{{ number_format((int) ($stats[$key] ?? 0)) }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@if (auth()->user()?->hasPermission('whatsapp.messages.index'))
    <div class="px-3 pb-3">
        <a href="{{ route('whatsapp.messages.index') }}">{{ trans('plugins/whatsapp::whatsapp.messages.title') }} &rarr;</a>
    </div>
@endif
