<?php

namespace Maina888\Whatsapp\Http\Requests\Settings;

use Botble\Base\Rules\OnOffRule;
use Botble\Support\Http\Requests\Request;

class WhatsappSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'whatsapp_enabled' => [new OnOffRule()],
            'whatsapp_api_version' => ['nullable', 'string', 'regex:/^v?\d+\.\d+$/'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'regex:/^\d{5,40}$/'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'regex:/^\d{5,40}$/'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_app_secret' => ['nullable', 'string', 'max:200'],
            'whatsapp_verify_token' => ['nullable', 'string', 'max:120'],
            'whatsapp_default_language' => ['nullable', 'string', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'whatsapp_default_country_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'whatsapp_sms_fallback' => [new OnOffRule()],
            'whatsapp_opt_out_confirmation' => [new OnOffRule()],
        ];
    }
}
