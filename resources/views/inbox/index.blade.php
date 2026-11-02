@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    <div class="row g-3">
        <div class="col-md-4">
            <x-core::card>
                <x-core::card.header>
                    <form method="GET" action="{{ route('whatsapp.inbox.index') }}" class="w-100">
                        <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="{{ trans('plugins/whatsapp::whatsapp.inbox.search') }}">
                    </form>
                </x-core::card.header>
                <div class="list-group list-group-flush" style="max-height: 70vh; overflow-y: auto">
                    @forelse ($conversations as $conversation)
                        <a href="{{ route('whatsapp.inbox.index', ['contact' => $conversation->getKey(), 'q' => $search ?: null]) }}"
                           class="list-group-item list-group-item-action {{ $contact && $contact->getKey() === $conversation->getKey() ? 'active' : '' }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-truncate">{{ $conversation->name ?: '+' . $conversation->phone }}</strong>
                                @if ($conversation->isWithinWindow())
                                    <span class="badge bg-success text-success-fg">24h</span>
                                @endif
                            </div>
                            <div class="small text-muted">
                                +{{ $conversation->phone }} &middot; {{ BaseHelper::formatDateTime($conversation->last_inbound_at) }}
                                @if ($conversation->opted_out)
                                    &middot; <span class="text-danger">STOP</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">{{ trans('plugins/whatsapp::whatsapp.inbox.empty') }}</div>
                    @endforelse
                </div>
                @if ($conversations->hasPages())
                    <x-core::card.footer>
                        {{ $conversations->links() }}
                    </x-core::card.footer>
                @endif
            </x-core::card>
        </div>

        <div class="col-md-8">
            <x-core::card>
                @if (! $contact)
                    <x-core::card.body>
                        <span class="text-muted">{{ trans('plugins/whatsapp::whatsapp.inbox.select') }}</span>
                    </x-core::card.body>
                @else
                    <x-core::card.header>
                        <div>
                            <x-core::card.title>{{ $contact->name ?: '+' . $contact->phone }}</x-core::card.title>
                            <div class="small text-muted">+{{ $contact->phone }}</div>
                        </div>
                    </x-core::card.header>
                    <x-core::card.body style="max-height: 60vh; overflow-y: auto">
                        @forelse ($thread as $message)
                            @php $outbound = $message->isOutbound(); @endphp
                            <div class="d-flex {{ $outbound ? 'justify-content-end' : 'justify-content-start' }} mb-2">
                                <div class="p-2 rounded {{ $outbound ? 'bg-success-lt' : 'bg-secondary-lt' }}" style="max-width: 80%">
                                    @if ($message->type === 'template')
                                        <div class="small text-muted"><i class="ti ti-template"></i> {{ $message->template_name }}</div>
                                    @endif
                                    <div style="white-space: pre-wrap">{{ $message->body }}</div>
                                    <div class="small text-muted text-end mt-1">
                                        {{ BaseHelper::formatDateTime($message->created_at) }}
                                        @if ($outbound)
                                            &middot; {!! $message->status->toHtml() !!}
                                            @if ($message->error)
                                                <div class="text-danger">{{ $message->error_code }}: {{ \Illuminate\Support\Str::limit($message->error, 120) }}</div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <span class="text-muted">{{ trans('plugins/whatsapp::whatsapp.inbox.no_messages') }}</span>
                        @endforelse
                    </x-core::card.body>
                    <x-core::card.footer>
                        @if ($contact->opted_out)
                            <x-core::alert type="warning" class="mb-2">{{ trans('plugins/whatsapp::whatsapp.inbox.opted_out') }}</x-core::alert>
                        @endif

                        @if ($contact->isWithinWindow())
                            @if (auth()->user()?->hasPermission('whatsapp.inbox.reply'))
                                <div class="small text-muted mb-2">
                                    {{ trans('plugins/whatsapp::whatsapp.inbox.window_open_until', ['time' => BaseHelper::formatDateTime($contact->windowClosesAt())]) }}
                                </div>
                                <form method="POST" action="{{ route('whatsapp.inbox.reply', $contact->getKey()) }}">
                                    @csrf
                                    <div class="input-group">
                                        <textarea name="message" class="form-control" rows="2" maxlength="4096" required placeholder="{{ trans('plugins/whatsapp::whatsapp.inbox.reply_placeholder') }}">{{ old('message') }}</textarea>
                                        <x-core::button type="submit" color="primary" icon="ti ti-send">
                                            {{ trans('plugins/whatsapp::whatsapp.inbox.reply') }}
                                        </x-core::button>
                                    </div>
                                </form>
                            @endif
                        @else
                            <span class="text-muted small">{{ trans('plugins/whatsapp::whatsapp.inbox.window_closed') }}</span>
                        @endif
                    </x-core::card.footer>
                @endif
            </x-core::card>
        </div>
    </div>
@endsection
