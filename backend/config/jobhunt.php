<?php

return [

    /*
    | Timezone used for weeks, apply days and everything shown in the UI.
    | Dates are still stored in the app timezone (UTC).
    */

    'timezone' => 'Asia/Kolkata',

    /*
    | Read-only in the UI. Edit here to change them.
    */

    'apply_rules' => [
        'apply_days' => ['saturday', 'sunday'],
        'locked_window' => ['day' => 'monday', 'start' => '06:30', 'end' => '11:30'],
    ],

    /*
    | Gmail sync. Only mail from these senders, or with these words in the subject,
    | is fetched. Companies you have applications with are added to the senders.
    */

    /*
    | Salary suggestions use only the posting's own range and your CTC from Settings.
    | This is the raise asked for on top of your current CTC.
    */

    'salary' => [
        'raise_low_percent' => 20,
        'raise_high_percent' => 40,
    ],

    'mail' => [
        'lookback_days' => 30,
        'max_messages_per_sync' => 500,
        'platform_domains' => ['naukri.com', 'linkedin.com', 'indeed.com', 'instahyre.com', 'wellfound.com', 'foundit.in'],
        'subject_words' => ['application', 'interview', 'assessment', 'shortlisted', 'offer', 'regret'],
        // Never treated as a company's domain, or every personal email would match.
        'ignored_domains' => ['gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'yahoo.com', 'yahoo.co.in', 'icloud.com', 'proton.me', 'protonmail.com', 'rediffmail.com'],
    ],

];
