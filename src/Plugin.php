<?php

namespace MainaSuperintelligence\Whatsapp;

use Botble\PluginManagement\Abstracts\PluginOperationAbstract;
use Botble\Setting\Facades\Setting;
use Botble\Setting\Models\Setting as SettingModel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Plugin extends PluginOperationAbstract
{
    public static function activated(): void
    {
        // A random webhook verify token so the owner only has to copy it into the Meta dashboard.
        if (! setting('whatsapp_verify_token')) {
            Setting::set('whatsapp_verify_token', Str::random(40))->save();
        }
    }

    public static function remove(): void
    {
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_contacts');

        $keys = SettingModel::query()->where('key', 'LIKE', 'whatsapp\_%')->pluck('key')->all();

        if ($keys) {
            Setting::delete($keys);
        }
    }
}
