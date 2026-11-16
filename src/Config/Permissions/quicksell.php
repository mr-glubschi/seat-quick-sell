<?php

// Registered with scope "quicksell" in QuickSellServiceProvider, so "use" becomes quicksell.use.
// Labels and descriptions must be translation keys (SeAT package development guide, "Permissions").
return [
    'use' => [
        'label' => 'quicksell::permissions.use_label',
        'description' => 'quicksell::permissions.use_description',
        'division' => 'financial',
    ],
];
