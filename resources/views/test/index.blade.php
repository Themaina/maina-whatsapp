@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    @if (! $configured)
        <x-core::alert type="warning">
            {{ trans('plugins/whatsapp::whatsapp.test.not_configured') }}
            <a href="{{ route('whatsapp.settings') }}">{{ trans('plugins/whatsapp::whatsapp.settings.title') }}</a>
        </x-core::alert>
    @endif

    <x-core::card>
        <x-core::card.header>
            <x-core::card.title>{{ trans('plugins/whatsapp::whatsapp.test.title') }}</x-core::card.title>
        </x-core::card.header>
        <x-core::card.body>
            <p class="text-muted">{{ trans('plugins/whatsapp::whatsapp.test.description') }}</p>

            <form method="POST" action="{{ route('whatsapp.test.send') }}">
                @csrf

                <x-core::form.text-input
                    name="phone"
                    :label="trans('plugins/whatsapp::whatsapp.test.phone')"
                    :value="old('phone')"
                    placeholder="0712 345 678"
                    :required="true"
                />

                <div class="mb-3">
                    <label class="form-label">{{ trans('plugins/whatsapp::whatsapp.test.mode') }}</label>
                    @foreach (['hello_world', 'template', 'text'] as $mode)
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="mode" value="{{ $mode }}" @checked(old('mode', 'hello_world') === $mode)>
                            <span class="form-check-label">{{ trans('plugins/whatsapp::whatsapp.test.mode_' . $mode) }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mb-3">
                    <label class="form-label" for="template_key">{{ trans('plugins/whatsapp::whatsapp.test.template') }}</label>
                    <select class="form-select" name="template_key" id="template_key">
                        <option value="">-</option>
                        @foreach ($definitions as $key => $definition)
                            <option value="{{ $key }}" @selected(old('template_key') === $key)>{{ $definition['label'] }} ({{ $key }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="text">{{ trans('plugins/whatsapp::whatsapp.test.text') }}</label>
                    <textarea class="form-control" name="text" id="text" rows="3" maxlength="4096">{{ old('text') }}</textarea>
                </div>

                <x-core::button type="submit" color="primary" icon="ti ti-send">
                    {{ trans('plugins/whatsapp::whatsapp.test.send') }}
                </x-core::button>
            </form>
        </x-core::card.body>
    </x-core::card>
@endsection
