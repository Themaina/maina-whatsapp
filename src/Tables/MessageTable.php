<?php

namespace MainaSuperintelligence\Whatsapp\Tables;

use Botble\Base\Facades\BaseHelper;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\Action;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\EnumColumn;
use Botble\Table\Columns\FormattedColumn;
use Botble\Table\Columns\IdColumn;
use MainaSuperintelligence\Whatsapp\Enums\MessageDirectionEnum;
use MainaSuperintelligence\Whatsapp\Enums\MessageStatusEnum;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use MainaSuperintelligence\Whatsapp\Supports\TemplateRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MessageTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(WhatsappMessage::class)
            ->addActions([
                Action::make('resend')
                    ->route('whatsapp.messages.resend')
                    ->permission('whatsapp.messages.resend')
                    ->icon('ti ti-refresh')
                    ->color('warning')
                    ->label(trans('plugins/whatsapp::whatsapp.messages.resend'))
                    ->action('POST')
                    ->renderUsing(fn (Action $action) => $action->getItem()->canBeResent() ? null : ''),
            ])
            ->addColumns([
                IdColumn::make(),
                EnumColumn::make('direction')
                    ->title(trans('plugins/whatsapp::whatsapp.messages.direction'))
                    ->width(80),
                FormattedColumn::make('phone')
                    ->title(trans('plugins/whatsapp::whatsapp.messages.phone'))
                    ->alignStart()
                    ->getValueUsing(function (FormattedColumn $column) {
                        /** @var WhatsappMessage $item */
                        $item = $column->getItem();
                        $name = $item->contact_id ? $item->contact?->name : null;

                        return e('+' . ltrim((string) $item->phone, '+')) . ($name ? '<br><small class="text-muted">' . e($name) . '</small>' : '');
                    }),
                FormattedColumn::make('template_key')
                    ->title(trans('plugins/whatsapp::whatsapp.messages.template'))
                    ->alignStart()
                    ->getValueUsing(function (FormattedColumn $column) {
                        /** @var WhatsappMessage $item */
                        $item = $column->getItem();

                        if ($item->type !== 'template') {
                            return e($item->type);
                        }

                        return e((string) $item->template_key)
                            . ($item->template_name ? '<br><small class="text-muted">' . e($item->template_name . ' (' . $item->language . ')') . '</small>' : '');
                    }),
                FormattedColumn::make('body')
                    ->title(trans('plugins/whatsapp::whatsapp.messages.body'))
                    ->alignStart()
                    ->orderable(false)
                    ->getValueUsing(fn (FormattedColumn $column) => e(Str::limit((string) $column->getItem()->body, 120))),
                EnumColumn::make('status')
                    ->title(trans('core/base::tables.status'))
                    ->width(100),
                FormattedColumn::make('error')
                    ->title(trans('plugins/whatsapp::whatsapp.messages.error'))
                    ->alignStart()
                    ->orderable(false)
                    ->getValueUsing(function (FormattedColumn $column) {
                        /** @var WhatsappMessage $item */
                        $item = $column->getItem();
                        $html = $item->error_code ? '<code>' . e($item->error_code) . '</code> ' . e(Str::limit((string) $item->error, 120)) : '';

                        if ($fallback = Arr::get((array) $item->context, 'sms_fallback.status')) {
                            $html .= '<br>' . BaseHelper::renderBadge(
                                trans('plugins/whatsapp::whatsapp.messages.sms_fallback', ['status' => $fallback]),
                                $fallback === 'sent' ? 'success' : 'secondary'
                            );
                        }

                        return $html;
                    }),
                CreatedAtColumn::make()->dateFormat(BaseHelper::getDateTimeFormat()),
            ])
            ->queryUsing(fn (Builder $query) => $query->with('contact')->latest('id'));
    }

    public function getFilters(): array
    {
        $templates = collect(TemplateRegistry::all())->mapWithKeys(fn ($definition, $key) => [$key => $definition['label']])->all();

        return [
            'status' => [
                'title' => trans('core/base::tables.status'),
                'type' => 'select',
                'choices' => MessageStatusEnum::labels(),
            ],
            'direction' => [
                'title' => trans('plugins/whatsapp::whatsapp.messages.direction'),
                'type' => 'select',
                'choices' => MessageDirectionEnum::labels(),
            ],
            'template_key' => [
                'title' => trans('plugins/whatsapp::whatsapp.messages.template'),
                'type' => 'select',
                'choices' => $templates,
            ],
            'phone' => [
                'title' => trans('plugins/whatsapp::whatsapp.messages.phone'),
                'type' => 'text',
            ],
            'error_code' => [
                'title' => trans('plugins/whatsapp::whatsapp.messages.error_code'),
                'type' => 'text',
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'type' => 'datePicker',
            ],
        ];
    }

    public function applyFilterCondition(
        \Illuminate\Database\Eloquent\Relations\Relation|Builder|\Illuminate\Database\Query\Builder $query,
        string $key,
        string $operator,
        ?string $value
    ): \Illuminate\Database\Eloquent\Relations\Relation|Builder|\Illuminate\Database\Query\Builder {
        if ($key === 'phone' && $value !== null && $value !== '') {
            $digits = preg_replace('/\D+/', '', $value);

            return $query->where('phone', 'LIKE', '%' . $digits . '%');
        }

        return parent::applyFilterCondition($query, $key, $operator, $value);
    }
}
