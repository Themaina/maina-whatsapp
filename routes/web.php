<?php

use Botble\Base\Facades\AdminHelper;
use MainaSuperintelligence\Whatsapp\Http\Controllers\ContactController;
use MainaSuperintelligence\Whatsapp\Http\Controllers\InboxController;
use MainaSuperintelligence\Whatsapp\Http\Controllers\MessageController;
use MainaSuperintelligence\Whatsapp\Http\Controllers\Settings\WhatsappSettingController;
use MainaSuperintelligence\Whatsapp\Http\Controllers\TemplateController;
use MainaSuperintelligence\Whatsapp\Http\Controllers\TestController;
use MainaSuperintelligence\Whatsapp\Http\Controllers\WebhookController;
use MainaSuperintelligence\Whatsapp\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

// Meta webhook: public, no session, no CSRF. POST requests are authenticated by X-Hub-Signature-256.
Route::get('whatsapp/webhook', [WebhookController::class, 'verify'])
    ->name('whatsapp.webhook.verify');

Route::post('whatsapp/webhook', [WebhookController::class, 'receive'])
    ->withoutMiddleware([
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
        'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'App\Http\Middleware\VerifyCsrfToken',
    ])
    ->middleware('throttle:600,1')
    ->name('whatsapp.webhook.receive');

AdminHelper::registerRoutes(function (): void {
    Route::group(['prefix' => 'whatsapp', 'as' => 'whatsapp.'], function (): void {
        Route::match(['GET', 'POST'], 'messages', [MessageController::class, 'index'])
            ->name('messages.index')
            ->permission('whatsapp.messages.index');

        Route::post('messages/{message}/resend', [MessageController::class, 'resend'])
            ->name('messages.resend')
            ->permission('whatsapp.messages.resend')
            ->wherePrimaryKey('message');

        Route::get('inbox', [InboxController::class, 'index'])
            ->name('inbox.index')
            ->permission('whatsapp.inbox.index');

        Route::post('inbox/{contact}/reply', [InboxController::class, 'reply'])
            ->name('inbox.reply')
            ->permission('whatsapp.inbox.reply')
            ->wherePrimaryKey('contact');

        Route::match(['GET', 'POST'], 'contacts', [ContactController::class, 'index'])
            ->name('contacts.index')
            ->permission('whatsapp.contacts.index');

        Route::post('contacts/{contact}/toggle-opt-out', [ContactController::class, 'toggleOptOut'])
            ->name('contacts.toggle-opt-out')
            ->permission('whatsapp.contacts.edit')
            ->wherePrimaryKey('contact');

        Route::get('templates', [TemplateController::class, 'index'])
            ->name('templates.index')
            ->permission('whatsapp.templates.index');

        Route::put('templates', [TemplateController::class, 'update'])
            ->name('templates.update')
            ->permission('whatsapp.templates.edit');

        Route::post('templates/sync', [TemplateController::class, 'sync'])
            ->name('templates.sync')
            ->permission('whatsapp.templates.edit');

        Route::get('test', [TestController::class, 'index'])
            ->name('test.index')
            ->permission('whatsapp.test');

        Route::post('test', [TestController::class, 'send'])
            ->name('test.send')
            ->permission('whatsapp.test');

        Route::get('widgets/stats', [WidgetController::class, 'stats'])
            ->name('widgets.stats')
            ->permission('whatsapp.messages.index');
    });

    Route::group(['prefix' => 'settings'], function (): void {
        Route::get('whatsapp', [WhatsappSettingController::class, 'edit'])
            ->name('whatsapp.settings')
            ->permission('whatsapp.settings');

        Route::put('whatsapp', [WhatsappSettingController::class, 'update'])
            ->name('whatsapp.settings.update')
            ->permission('whatsapp.settings');
    });
});
