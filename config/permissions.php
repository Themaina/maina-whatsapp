<?php

return [
    [
        'name' => 'WhatsApp',
        'flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Messages',
        'flag' => 'whatsapp.messages.index',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Resend messages',
        'flag' => 'whatsapp.messages.resend',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Inbox',
        'flag' => 'whatsapp.inbox.index',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Reply in inbox',
        'flag' => 'whatsapp.inbox.reply',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Contacts',
        'flag' => 'whatsapp.contacts.index',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Edit contacts (opt out / in)',
        'flag' => 'whatsapp.contacts.edit',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Templates',
        'flag' => 'whatsapp.templates.index',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Edit template mapping and sync',
        'flag' => 'whatsapp.templates.edit',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'Send test message',
        'flag' => 'whatsapp.test',
        'parent_flag' => 'whatsapp.index',
    ],
    [
        'name' => 'WhatsApp',
        'flag' => 'whatsapp.settings',
        'parent_flag' => 'settings.others',
    ],
];
