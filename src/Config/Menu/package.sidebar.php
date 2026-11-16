<?php

// Sidebar entry (format documented in SeAT's package development guide, "Sidebar menu").
// One top-level link without sub-entries, like SeAT's own "Home" entry.
return [
    'quicksell' => [
        'name' => 'quick sell',
        'label' => 'quicksell::menu.main',
        'icon' => 'fas fa-tags',
        'route_segment' => 'quick-sell',
        'route' => 'seatquicksell::home',
        'permission' => 'quicksell.use',
    ],
];
