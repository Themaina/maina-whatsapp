<?php

namespace Maina888\Whatsapp\Providers;

use Botble\Base\Facades\DashboardMenu;
use Botble\Base\Facades\PanelSectionManager;
use Botble\Base\PanelSections\PanelSectionItem;
use Botble\Base\Supports\DashboardMenuItem;
use Botble\Base\Supports\ServiceProvider;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Botble\Setting\PanelSections\SettingOthersPanelSection;
use Maina888\Whatsapp\Commands\SendTestCommand;
use Maina888\Whatsapp\Commands\SyncTemplatesCommand;
use Maina888\Whatsapp\Services\CloudApiClient;
use Maina888\Whatsapp\Services\WebhookProcessor;
use Maina888\Whatsapp\Services\WhatsappService;
use Maina888\Whatsapp\Supports\BuiltInTemplates;
use Maina888\Whatsapp\Supports\TemplateRegistry;

class WhatsappServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->singleton(CloudApiClient::class);
        $this->app->singleton(WhatsappService::class);
        $this->app->singleton(WebhookProcessor::class);
    }

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/whatsapp')
            ->loadHelpers()
            ->loadAndPublishConfigurations(['general', 'permissions'])
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->loadRoutes()
            ->loadMigrations();

        // Built-in definitions first (priority 1) so other plugins' definitions win on the same key.
        add_filter(TemplateRegistry::FILTER, [BuiltInTemplates::class, 'register'], 1);

        $this->app->register(EventServiceProvider::class);
        $this->app->register(HookServiceProvider::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncTemplatesCommand::class, SendTestCommand::class]);
        }

        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::make()
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-plugins-whatsapp')
                        ->priority(431)
                        ->name('plugins/whatsapp::whatsapp.name')
                        ->icon('ti ti-brand-whatsapp')
                        ->permissions(['whatsapp.index'])
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-plugins-whatsapp-messages')
                        ->parentId('cms-plugins-whatsapp')
                        ->priority(1)
                        ->name('plugins/whatsapp::whatsapp.messages.title')
                        ->icon('ti ti-list')
                        ->route('whatsapp.messages.index')
                        ->permissions(['whatsapp.messages.index'])
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-plugins-whatsapp-inbox')
                        ->parentId('cms-plugins-whatsapp')
                        ->priority(2)
                        ->name('plugins/whatsapp::whatsapp.inbox.title')
                        ->icon('ti ti-inbox')
                        ->route('whatsapp.inbox.index')
                        ->permissions(['whatsapp.inbox.index'])
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-plugins-whatsapp-contacts')
                        ->parentId('cms-plugins-whatsapp')
                        ->priority(3)
                        ->name('plugins/whatsapp::whatsapp.contacts.title')
                        ->icon('ti ti-address-book')
                        ->route('whatsapp.contacts.index')
                        ->permissions(['whatsapp.contacts.index'])
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-plugins-whatsapp-templates')
                        ->parentId('cms-plugins-whatsapp')
                        ->priority(4)
                        ->name('plugins/whatsapp::whatsapp.templates.title')
                        ->icon('ti ti-template')
                        ->route('whatsapp.templates.index')
                        ->permissions(['whatsapp.templates.index'])
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-plugins-whatsapp-test')
                        ->parentId('cms-plugins-whatsapp')
                        ->priority(5)
                        ->name('plugins/whatsapp::whatsapp.test.title')
                        ->icon('ti ti-send')
                        ->route('whatsapp.test.index')
                        ->permissions(['whatsapp.test'])
                )
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-plugins-whatsapp-settings')
                        ->parentId('cms-plugins-whatsapp')
                        ->priority(6)
                        ->name('plugins/whatsapp::whatsapp.settings.title')
                        ->icon('ti ti-settings')
                        ->route('whatsapp.settings')
                        ->permissions(['whatsapp.settings'])
                );
        });

        PanelSectionManager::default()->beforeRendering(function (): void {
            PanelSectionManager::registerItem(
                SettingOthersPanelSection::class,
                fn () => PanelSectionItem::make('whatsapp')
                    ->setTitle(trans('plugins/whatsapp::whatsapp.settings.title'))
                    ->withIcon('ti ti-brand-whatsapp')
                    ->withDescription(trans('plugins/whatsapp::whatsapp.settings.panel_description'))
                    ->withPriority(150)
                    ->withRoute('whatsapp.settings')
            );
        });
    }
}
