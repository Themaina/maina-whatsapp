<div class="mb-3">
    <label class="form-label">{{ trans('plugins/whatsapp::whatsapp.settings.webhook_url') }}</label>
    <div class="input-group">
        <input type="text" class="form-control" value="{{ route('whatsapp.webhook.receive') }}" readonly>
        <span class="input-group-text">
            <x-core::copy :copyableState="route('whatsapp.webhook.receive')" />
        </span>
    </div>
    <small class="form-hint">{{ trans('plugins/whatsapp::whatsapp.settings.webhook_url_helper') }}</small>
</div>
