<?php

namespace Maina888\Whatsapp\Tables;

use Botble\Base\Facades\BaseHelper;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\Action;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\FormattedColumn;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\YesNoColumn;
use Maina888\Whatsapp\Models\WhatsappContact;
use Illuminate\Database\Eloquent\Builder;

class ContactTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(WhatsappContact::class)
            ->addActions([
                Action::make('conversation')
                    ->url(fn (Action $action) => route('whatsapp.inbox.index', ['contact' => $action->getItem()->getKey()]))
                    ->permission('whatsapp.inbox.index')
                    ->icon('ti ti-messages')
                    ->color('info')
                    ->label(trans('plugins/whatsapp::whatsapp.inbox.open')),
                Action::make('toggle-opt-out')
                    ->route('whatsapp.contacts.toggle-opt-out')
                    ->permission('whatsapp.contacts.edit')
                    ->icon('ti ti-ban')
                    ->color('warning')
                    ->label(trans('plugins/whatsapp::whatsapp.contacts.toggle_opt_out'))
                    ->action('POST'),
            ])
            ->addColumns([
                IdColumn::make(),
                FormattedColumn::make('phone')
                    ->title(trans('plugins/whatsapp::whatsapp.messages.phone'))
                    ->alignStart()
                    ->getValueUsing(fn (FormattedColumn $column) => e('+' . $column->getItem()->phone)),
                FormattedColumn::make('name')
                    ->title(trans('core/base::tables.name'))
                    ->alignStart()
                    ->getValueUsing(function (FormattedColumn $column) {
                        $item = $column->getItem();
                        $html = e((string) $item->name);

                        if ($item->customer_id && is_plugin_active('ecommerce') && auth()->user()?->hasPermission('customers.edit')) {
                            $html .= ' <a href="' . e(route('customers.edit', $item->customer_id)) . '" class="small">#' . (int) $item->customer_id . '</a>';
                        }

                        return $html;
                    }),
                YesNoColumn::make('opted_out')
                    ->title(trans('plugins/whatsapp::whatsapp.contacts.opted_out'))
                    ->width(100),
                FormattedColumn::make('last_inbound_at')
                    ->title(trans('plugins/whatsapp::whatsapp.contacts.last_inbound_at'))
                    ->getValueUsing(function (FormattedColumn $column) {
                        /** @var WhatsappContact $item */
                        $item = $column->getItem();

                        if (! $item->last_inbound_at) {
                            return '&mdash;';
                        }

                        return e(BaseHelper::formatDateTime($item->last_inbound_at))
                            . ($item->isWithinWindow() ? ' ' . BaseHelper::renderBadge(trans('plugins/whatsapp::whatsapp.inbox.window_open'), 'success') : '');
                    }),
                CreatedAtColumn::make(),
            ])
            ->queryUsing(fn (Builder $query) => $query->latest('id'));
    }

    public function getFilters(): array
    {
        return [
            'phone' => ['title' => trans('plugins/whatsapp::whatsapp.messages.phone'), 'type' => 'text'],
            'name' => ['title' => trans('core/base::tables.name'), 'type' => 'text'],
            'opted_out' => [
                'title' => trans('plugins/whatsapp::whatsapp.contacts.opted_out'),
                'type' => 'select',
                'choices' => [0 => trans('core/base::base.no'), 1 => trans('core/base::base.yes')],
            ],
        ];
    }
}
