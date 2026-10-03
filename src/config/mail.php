<?php

/**
 * Mail configuration.
 *
 * CHANGED: credentials now come from Vercel environment variables
 * instead of being stored directly in the repository.
 */
return [
    // CHANGED: Gmail account used to authenticate with SMTP.
    'smtp_email' => getenv('SMTP_EMAIL') ?: '',

    // CHANGED: Gmail App Password stored securely in Vercel.
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',

    // CHANGED: sender address is now deployment-configurable.
    'smtp_from_email' => getenv('SMTP_FROM_EMAIL')
        ?: getenv('SMTP_EMAIL')
        ?: '',

    // CHANGED: sender display name is configurable without code changes.
    'smtp_from_name' => getenv('SMTP_FROM_NAME')
        ?: 'Events Portal',

    // CHANGED: contact-form recipient is stored in the environment.
    'contact_recipient' => getenv('CONTACT_RECIPIENT')
        ?: getenv('SMTP_EMAIL')
        ?: '',
];