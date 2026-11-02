# Changelog

## 1.1.0
- First public release on the Botble Marketplace as **WhatsApp Business Cloud API**, free under the MIT License.
- Vendor namespace is now `MainaSuperintelligence\Whatsapp` and the plugin id is `maina888/whatsapp`.
- Works on any Botble shop: project-specific templates removed; the built-in templates are the ecommerce order notifications with neutral suggested text.
- STOP/START confirmation messages use your site name.
- New *Default country code* setting (falls back to the store country) instead of a fixed Kenyan default.
- The SMS fallback is only used when the SMS via Africa's Talking plugin is active, and the settings page says so when it isn't.
- Webhook route works on Laravel 12 and 13.
- Unit tests run with only the project's vendor directory; feature tests are a separate suite. The database self-test script was removed.

## 1.0.0
- Meta WhatsApp Business Cloud API sending with encrypted credentials.
- Order notifications mapped to approved templates, template sync from Meta.
- Inbox with 24-hour window, message log with receipts and resend, contacts with STOP/START opt-out.
- Signed webhook, queued sending with retries, SMS fallback, dashboard widget, test page and commands.
