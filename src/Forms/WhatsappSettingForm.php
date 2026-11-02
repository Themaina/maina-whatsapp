<?php

namespace MainaSuperintelligence\Whatsapp\Forms;

use Botble\Base\Forms\FieldOptions\AlertFieldOption;
use Botble\Base\Forms\FieldOptions\CheckboxFieldOption;
use Botble\Base\Forms\FieldOptions\HtmlFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\AlertField;
use Botble\Base\Forms\Fields\HtmlField;
use Botble\Base\Forms\Fields\OnOffCheckboxField;
use Botble\Base\Forms\Fields\PasswordField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Setting\Forms\SettingForm;
use MainaSuperintelligence\Whatsapp\Http\Requests\Settings\WhatsappSettingRequest;
use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;

class WhatsappSettingForm extends SettingForm
{
    public function setup(): void
    {
        parent::setup();

        $tokenSaved = WhatsappSettings::hasValue('whatsapp_access_token');
        $secretSaved = WhatsappSettings::hasValue('whatsapp_app_secret');

        $this
            ->setSectionTitle(trans('plugins/whatsapp::whatsapp.settings.title'))
            ->setSectionDescription(trans('plugins/whatsapp::whatsapp.settings.description'))
            ->setValidatorClass(WhatsappSettingRequest::class)
            ->add(
                'whatsapp_enabled',
                OnOffCheckboxField::class,
                CheckboxFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.enabled'))
                    ->helperText(trans('plugins/whatsapp::whatsapp.settings.enabled_helper'))
                    ->value(WhatsappSettings::enabled())
            )
            ->add(
                'whatsapp_api_version',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.api_version'))
                    ->value(WhatsappSettings::apiVersion())
                    ->placeholder('v21.0')
                    ->maxLength(10)
            )
            ->add(
                'whatsapp_phone_number_id',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.phone_number_id'))
                    ->value(WhatsappSettings::phoneNumberId())
                    ->helperText(trans('plugins/whatsapp::whatsapp.settings.phone_number_id_helper'))
                    ->maxLength(40)
            )
            ->add(
                'whatsapp_business_account_id',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.business_account_id'))
                    ->value(WhatsappSettings::businessAccountId())
                    ->maxLength(40)
            )
            ->add(
                'whatsapp_access_token',
                PasswordField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.access_token'))
                    ->value('')
                    ->placeholder($tokenSaved ? trans('plugins/whatsapp::whatsapp.settings.saved_keep') : '')
                    ->helperText(trans('plugins/whatsapp::whatsapp.settings.access_token_helper'))
                    ->maxLength(1000)
            )
            ->add(
                'whatsapp_app_secret',
                PasswordField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.app_secret'))
                    ->value('')
                    ->placeholder($secretSaved ? trans('plugins/whatsapp::whatsapp.settings.saved_keep') : '')
                    ->helperText(trans('plugins/whatsapp::whatsapp.settings.app_secret_helper'))
                    ->maxLength(200)
            )
            ->add(
                'whatsapp_verify_token',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.verify_token'))
                    ->value(WhatsappSettings::verifyToken())
                    ->helperText(trans('plugins/whatsapp::whatsapp.settings.verify_token_helper'))
                    ->maxLength(120)
            )
            ->add(
                'whatsapp_webhook_url',
                HtmlField::class,
                HtmlFieldOption::make()->content(view('plugins/whatsapp::settings.webhook-url')->render())
            )
            ->add(
                'whatsapp_default_language',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.default_language'))
                    ->value(WhatsappSettings::defaultLanguage())
                    ->helperText(trans('plugins/whatsapp::whatsapp.settings.default_language_helper'))
                    ->maxLength(15)
            )
            ->add(
                'whatsapp_default_country_code',
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.default_country_code'))
                    ->value(setting('whatsapp_default_country_code'))
                    ->placeholder(WhatsappSettings::defaultCountryCode() ?: '254')
                    ->helperText(trans('plugins/whatsapp::whatsapp.settings.default_country_code_helper'))
                    ->maxLength(4)
            )
            ->add(
                'whatsapp_sms_fallback',
                OnOffCheckboxField::class,
                CheckboxFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.sms_fallback'))
                    ->helperText(trans('plugins/whatsapp::whatsapp.settings.sms_fallback_helper')
                        . (WhatsappService::smsAvailable() ? '' : ' ' . trans('plugins/whatsapp::whatsapp.settings.sms_fallback_unavailable')))
                    ->value(WhatsappSettings::smsFallback())
            )
            ->add(
                'whatsapp_opt_out_confirmation',
                OnOffCheckboxField::class,
                CheckboxFieldOption::make()
                    ->label(trans('plugins/whatsapp::whatsapp.settings.opt_out_confirmation'))
                    ->value(WhatsappSettings::sendOptOutConfirmation())
            )
            ->add(
                'whatsapp_events_alert',
                AlertField::class,
                AlertFieldOption::make()
                    ->type('info')
                    ->content(trans('plugins/whatsapp::whatsapp.settings.events_helper', [
                        'url' => route('whatsapp.templates.index'),
                    ]))
            );
    }
}
