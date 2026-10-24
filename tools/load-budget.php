<?php
// Read by `wp kaupang-assets audit --budget`. Unlisted page types fall back to '*', then to the default (2 files, nothing else).
// Pages that aren't pac-managed load the main file only. A managed post loads PAC_Assets + PAC_Asset_Path to enqueue its
// own CSS/JS (those files live in the pages root, so the audit doesn't attribute them to this plugin). On staging the
// front page, the `pac` page and the `faq` product are managed. Per-file reasons: README "Load map".
$managed = ['files' => 3, 'handles' => [], 'modules' => []];

return [
    'autoload' => [],
    'pages'    => [
        '*'          => ['files' => 2, 'handles' => [], 'modules' => []],
        'front_page' => $managed,
        'pac'        => $managed,
        'faq'        => $managed,
    ],
];
