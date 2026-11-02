# WhatsApp plugin (Meta WhatsApp Business Cloud API)

Sends NattyGym notifications over WhatsApp, receives replies, and falls back to SMS through the `sms` plugin.

## Using it from another plugin

```php
// Register a template (key, ordered body variables, optional URL button variable)
add_filter('whatsapp_templates', function (array $templates) {
    $templates['membership_expiring'] = [
        'label' => 'Membership expiring',
        'template_name' => 'membership_expiring',          // suggested Meta template name
        'language' => 'en',
        'variables' => ['customer_name', 'plan_name', 'days_left', 'ends_at'],   // {{1}}..{{4}}
        'button_variable' => null,                          // or e.g. 'renew_path'
        'preview' => 'Hi {{customer_name}}, your {{plan_name}} ends in {{days_left}} days on {{ends_at}}.',
    ];

    return $templates;
}, 30);

// Send (queued). Falls back to SMS when WhatsApp is off, the event is disabled, the contact opted out,
// or Meta rejects the message (e.g. 131026 not on WhatsApp), if "Fall back to SMS" is on.
whatsapp_send_template($phone, 'membership_expiring', $vars, ['related' => $membership, 'customer_id' => $id]);

// Free text, only inside the 24 h window after the customer last wrote to us.
whatsapp_send_text($phone, 'We open at 05:00.');
```

Aliases accepted in definitions: `template` for `template_name`, `url_variable` for `button_variable`, `default` for `preview`.

Events: `WhatsappMessageSent`, `WhatsappMessageFailed`, `WhatsappMessageReceived`, `WhatsappMessageStatusUpdated`, `WhatsappContactOptedOut`.

## Meta setup (owner)

1. business.facebook.com: verify the business (Natty Gyms), create a WhatsApp Business Account.
2. developers.facebook.com: create an app of type Business, add the WhatsApp product, add the phone number +254 793 333 222 (it must not be registered on the WhatsApp or WhatsApp Business phone app at the same time; migrate or use a new number). Set the display name and verify the number by SMS/voice code.
3. Business Settings > System users: create a system user (Admin), assign the app and the WhatsApp account with full control, generate a token with `whatsapp_business_messaging` and `whatsapp_business_management` and no expiry. Paste it into Admin > Settings > WhatsApp > Permanent access token.
4. Copy Phone number ID and WhatsApp Business Account ID from WhatsApp > API Setup. Copy App secret from App settings > Basic.
5. WhatsApp > Configuration > Webhook: callback URL `https://nattygyms.com/whatsapp/webhook`, verify token from the settings page, then Subscribe to the `messages` field. The site must be reachable over HTTPS (not `nattygym.test`).
6. Add a payment method in WhatsApp Manager (utility conversations are billed per message).
7. Create the templates below in WhatsApp Manager (category Utility, language English `en`), wait for approval, then click "Sync from Meta" on Admin > WhatsApp > Templates, map each notification and switch it on.
8. Send Admin > WhatsApp > Send test > hello_world to your own phone.

## Tests

```
vendor/bin/phpunit -c platform/plugins/whatsapp/phpunit.xml      # from botble/
```

Feature tests run inside rolled-back transactions with the Graph API faked: webhook handshake and
`X-Hub-Signature-256` checks, status updates, STOP/START, the 24 hour window, the SMS fallback
(`SmsService::send` signature) and the `whatsapp_templates` contract with the gym and mpesa plugins.

## Local testing

The self test below saves WhatsApp settings for its run (and restores them), so do not run it while
other people use the same database; the PHPUnit suite covers the same ground without saving anything.

```
php artisan tinker --execute="require base_path('platform/plugins/whatsapp/tests/selftest.php');"
```

Runs the full flow against the local database with the Graph API faked, then removes its rows and restores settings.
