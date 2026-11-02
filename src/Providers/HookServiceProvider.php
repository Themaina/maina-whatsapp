<?php

namespace Maina888\Whatsapp\Providers;

use Botble\Dashboard\Events\RenderingDashboardWidgets;
use Botble\Dashboard\Supports\DashboardWidgetInstance;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['events']->listen(RenderingDashboardWidgets::class, function (): void {
            add_filter(DASHBOARD_FILTER_ADMIN_LIST, [$this, 'addStatsWidget'], 240, 2);
        });
    }

    public function addStatsWidget(array $widgets, Collection $widgetSettings): array
    {
        return (new DashboardWidgetInstance())
            ->setPermission('whatsapp.messages.index')
            ->setKey('widget_whatsapp_today')
            ->setTitle(trans('plugins/whatsapp::whatsapp.widget.title'))
            ->setIcon('ti ti-brand-whatsapp')
            ->setColor('#25D366')
            ->setRoute(route('whatsapp.widgets.stats'))
            ->setBodyClass('')
            ->setColumn('col-md-6 col-sm-6')
            ->init($widgets, $widgetSettings);
    }
}
