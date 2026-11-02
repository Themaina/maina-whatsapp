<?php

namespace MainaSuperintelligence\Whatsapp\Http\Controllers\Settings;

use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Base\Supports\Breadcrumb;
use Botble\Setting\Http\Controllers\SettingController;
use MainaSuperintelligence\Whatsapp\Forms\WhatsappSettingForm;
use MainaSuperintelligence\Whatsapp\Http\Requests\Settings\WhatsappSettingRequest;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;
use Illuminate\Support\Arr;

class WhatsappSettingController extends SettingController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()->add(trans('core/base::base.panel.others'));
    }

    public function edit()
    {
        $this->pageTitle(trans('plugins/whatsapp::whatsapp.settings.title'));

        return WhatsappSettingForm::create()->renderForm();
    }

    public function update(WhatsappSettingRequest $request): BaseHttpResponse
    {
        $data = $request->validated();

        // Secrets: an empty field keeps the stored value; a new value is stored encrypted.
        foreach (WhatsappSettings::ENCRYPTED_KEYS as $key) {
            $value = trim((string) Arr::get($data, $key, ''));

            if ($value === '') {
                unset($data[$key]);

                continue;
            }

            $data[$key] = WhatsappSettings::encrypt($value);
        }

        if (isset($data['whatsapp_api_version']) && $data['whatsapp_api_version'] !== '' && ! str_starts_with($data['whatsapp_api_version'], 'v')) {
            $data['whatsapp_api_version'] = 'v' . $data['whatsapp_api_version'];
        }

        if (array_key_exists('whatsapp_default_country_code', $data)) {
            $data['whatsapp_default_country_code'] = ltrim(trim((string) $data['whatsapp_default_country_code']), '+');
        }

        foreach (['whatsapp_phone_number_id', 'whatsapp_business_account_id', 'whatsapp_verify_token', 'whatsapp_default_language'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = trim((string) $data[$key]);
            }
        }

        return $this->performUpdate($data);
    }
}
