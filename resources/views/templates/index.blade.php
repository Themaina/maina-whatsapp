@extends(BaseHelper::getAdminMasterLayoutTemplate())

@php
    $metaByKey = $metaTemplates->keyBy(fn ($template) => $template->name . '|' . $template->language);
    $canEdit = auth()->user()?->hasPermission('whatsapp.templates.edit');
@endphp

@section('content')
    <x-core::card class="mb-3">
        <x-core::card.header>
            <x-core::card.title>{{ trans('plugins/whatsapp::whatsapp.templates.title') }}</x-core::card.title>
            @if ($canEdit)
                <x-core::card.actions>
                    <form method="POST" action="{{ route('whatsapp.templates.sync') }}" class="d-inline">
                        @csrf
                        <x-core::button type="submit" icon="ti ti-refresh">
                            {{ trans('plugins/whatsapp::whatsapp.templates.sync') }}
                        </x-core::button>
                    </form>
                </x-core::card.actions>
            @endif
        </x-core::card.header>
        <x-core::card.body>
            <p class="text-muted mb-0">{{ trans('plugins/whatsapp::whatsapp.templates.description') }}</p>
        </x-core::card.body>
    </x-core::card>

    @if (! $definitions)
        <x-core::alert type="info">{{ trans('plugins/whatsapp::whatsapp.templates.none') }}</x-core::alert>
    @else
        <form method="POST" action="{{ route('whatsapp.templates.update') }}">
            @csrf
            @method('PUT')

            <datalist id="whatsapp-meta-templates">
                @foreach ($metaTemplates->unique('name') as $template)
                    <option value="{{ $template->name }}">{{ $template->status }}</option>
                @endforeach
            </datalist>

            @foreach ($groups as $group => $items)
                <x-core::card class="mb-3">
                    <x-core::card.header>
                        <x-core::card.title>{{ $group }}</x-core::card.title>
                    </x-core::card.header>
                    <div class="table-responsive">
                        <table class="table card-table table-vcenter">
                            <thead>
                                <tr>
                                    <th style="width: 60px">{{ trans('plugins/whatsapp::whatsapp.templates.enabled') }}</th>
                                    <th>{{ trans('plugins/whatsapp::whatsapp.templates.event') }}</th>
                                    <th>{{ trans('plugins/whatsapp::whatsapp.templates.meta_template') }}</th>
                                    <th style="width: 120px">{{ trans('plugins/whatsapp::whatsapp.templates.language') }}</th>
                                    <th>{{ trans('plugins/whatsapp::whatsapp.templates.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $key => $definition)
                                    @php
                                        $mapping = $mappings[$key];
                                        $meta = $metaByKey->get($mapping['name'] . '|' . $mapping['language']);
                                    @endphp
                                    <tr>
                                        <td>
                                            <input type="hidden" name="templates[{{ $key }}][enabled]" value="0">
                                            <label class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="templates[{{ $key }}][enabled]" value="1" @checked($mapping['enabled']) @disabled(! $canEdit)>
                                            </label>
                                        </td>
                                        <td>
                                            <strong>{{ $definition['label'] }}</strong>
                                            <div class="small text-muted"><code>{{ $key }}</code></div>
                                            <div class="small mt-1">
                                                {{ trans('plugins/whatsapp::whatsapp.templates.variables') }}:
                                                @forelse ($definition['variables'] as $index => $variable)
                                                    <span class="badge bg-secondary-lt">&#123;&#123;{{ $index + 1 }}&#125;&#125; {{ $variable }}</span>
                                                @empty
                                                    &mdash;
                                                @endforelse
                                            </div>
                                            @if ($definition['button_variable'])
                                                <div class="small mt-1">
                                                    {{ trans('plugins/whatsapp::whatsapp.templates.button') }}:
                                                    <span class="badge bg-azure-lt">{{ $definition['button_variable'] }}</span>
                                                    @if ($definition['button_url'])
                                                        <span class="text-muted">{{ url($definition['button_url']) }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                            @if ($definition['preview'])
                                                <details class="small mt-1">
                                                    <summary>{{ trans('plugins/whatsapp::whatsapp.templates.suggested_text') }}</summary>
                                                    <div class="text-muted mt-1" style="white-space: pre-wrap">{{ \Maina888\Whatsapp\Supports\TemplateRegistry::placeholderPreview($definition) }}</div>
                                                </details>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" list="whatsapp-meta-templates"
                                                name="templates[{{ $key }}][name]" value="{{ old('templates.' . $key . '.name', $mapping['name']) }}"
                                                placeholder="{{ $definition['template_name'] }}" pattern="[a-z0-9_]*" @disabled(! $canEdit)>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" name="templates[{{ $key }}][language]"
                                                value="{{ old('templates.' . $key . '.language', $mapping['language']) }}" placeholder="en" @disabled(! $canEdit)>
                                        </td>
                                        <td class="small">
                                            @if ($metaTemplates->isEmpty())
                                                <span class="text-muted">&mdash;</span>
                                            @elseif (! $meta)
                                                <span class="text-warning">{{ trans('plugins/whatsapp::whatsapp.templates.not_found') }}</span>
                                            @elseif (! $meta->isApproved())
                                                <span class="text-danger">{{ trans('plugins/whatsapp::whatsapp.templates.not_approved', ['status' => $meta->status]) }}</span>
                                            @elseif ($meta->body_variables !== count($definition['variables']))
                                                <span class="text-danger">{{ trans('plugins/whatsapp::whatsapp.templates.variable_mismatch', ['meta' => $meta->body_variables, 'ours' => count($definition['variables'])]) }}</span>
                                            @elseif ($definition['button_variable'] && ! $meta->has_url_button)
                                                <span class="text-warning">{{ trans('plugins/whatsapp::whatsapp.templates.button_mismatch') }}</span>
                                            @else
                                                <span class="text-success">{{ trans('plugins/whatsapp::whatsapp.templates.ok') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-core::card>
            @endforeach

            @if ($canEdit)
                <x-core::button type="submit" color="primary" icon="ti ti-device-floppy" class="mb-3">
                    {{ trans('plugins/whatsapp::whatsapp.templates.save') }}
                </x-core::button>
            @endif
        </form>
    @endif

    <x-core::card>
        <x-core::card.header>
            <x-core::card.title>{{ trans('plugins/whatsapp::whatsapp.templates.synced_title') }}</x-core::card.title>
        </x-core::card.header>
        @if ($metaTemplates->isEmpty())
            <x-core::card.body>
                <span class="text-muted">{{ trans('plugins/whatsapp::whatsapp.templates.never_synced') }}</span>
            </x-core::card.body>
        @else
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>{{ trans('core/base::tables.name') }}</th>
                            <th>{{ trans('plugins/whatsapp::whatsapp.templates.language') }}</th>
                            <th>{{ trans('plugins/whatsapp::whatsapp.templates.category') }}</th>
                            <th>{{ trans('plugins/whatsapp::whatsapp.templates.status') }}</th>
                            <th>{{ trans('plugins/whatsapp::whatsapp.messages.body') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($metaTemplates as $template)
                            <tr>
                                <td><code>{{ $template->name }}</code></td>
                                <td>{{ $template->language }}</td>
                                <td>{{ $template->category }}</td>
                                <td>
                                    <span class="badge {{ $template->isApproved() ? 'bg-success text-success-fg' : 'bg-secondary text-secondary-fg' }}">{{ $template->status }}</span>
                                </td>
                                <td class="small text-muted" style="white-space: pre-wrap">{{ \Illuminate\Support\Str::limit((string) $template->body, 300) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-core::card>
@endsection
