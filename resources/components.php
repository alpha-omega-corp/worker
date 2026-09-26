<?php

/*
 * What each kit component needs beyond its own file.
 *
 * The real dependencies are in the markup: pagination renders <x-kit.button>,
 * so importing pagination alone gives a page that fails to compile, and
 * nothing in the stylesheet says so. So the graph is written down here, one
 * entry per component the ui-kit package ships under
 * resources/views/components/kit:
 *
 *   requires  the other kit components its markup renders
 *   js        the npm package it needs in resources/js/app.js, or null
 *   blade     the file under components/kit it is published as
 *
 * `php artisan ui:import` and `ui:layout` read this; a test fails when a
 * component the package ships is missing here, or an entry names one it does
 * not ship.
 */

return [
    'action-panel' => ['requires' => [], 'js' => null, 'blade' => 'action-panel'],
    'alert' => ['requires' => [], 'js' => null, 'blade' => 'alert'],
    'avatar' => ['requires' => [], 'js' => null, 'blade' => 'avatar'],
    'badge' => ['requires' => [], 'js' => null, 'blade' => 'badge'],
    'breadcrumb' => ['requires' => [], 'js' => null, 'blade' => 'breadcrumb'],
    'button' => ['requires' => [], 'js' => null, 'blade' => 'button'],
    'button-group' => ['requires' => [], 'js' => null, 'blade' => 'button-group'],
    'calendar' => ['requires' => [], 'js' => null, 'blade' => 'calendar'],
    'card-heading' => ['requires' => [], 'js' => null, 'blade' => 'card-heading'],
    'catalogue' => ['requires' => ['button'], 'js' => null, 'blade' => 'catalogue'],
    'checkbox' => ['requires' => [], 'js' => null, 'blade' => 'checkbox'],
    'combobox' => ['requires' => [], 'js' => null, 'blade' => 'combobox'],
    'command-palette' => ['requires' => [], 'js' => null, 'blade' => 'command-palette'],
    'description-list' => ['requires' => [], 'js' => null, 'blade' => 'description-list'],
    'drawer' => ['requires' => [], 'js' => null, 'blade' => 'drawer'],
    'dropdown' => ['requires' => [], 'js' => '@tailwindplus/elements', 'blade' => 'dropdown'],
    'empty-state' => ['requires' => [], 'js' => null, 'blade' => 'empty-state'],
    'feed' => ['requires' => [], 'js' => null, 'blade' => 'feed'],
    'form-layout' => ['requires' => [], 'js' => null, 'blade' => 'form-layout'],
    'grid-list' => ['requires' => ['avatar'], 'js' => null, 'blade' => 'grid-list'],
    'input' => ['requires' => [], 'js' => null, 'blade' => 'input'],
    'map' => ['requires' => [], 'js' => null, 'blade' => 'map'],
    'menu' => ['requires' => [], 'js' => null, 'blade' => 'menu'],
    'modal' => ['requires' => [], 'js' => null, 'blade' => 'modal'],
    'navbar' => ['requires' => [], 'js' => null, 'blade' => 'navbar'],
    'notification' => ['requires' => [], 'js' => null, 'blade' => 'notification'],
    'page-heading' => ['requires' => [], 'js' => null, 'blade' => 'page-heading'],
    'pagination' => ['requires' => ['button'], 'js' => null, 'blade' => 'pagination'],
    'placeholder' => ['requires' => [], 'js' => null, 'blade' => 'placeholder'],
    'progress' => ['requires' => [], 'js' => null, 'blade' => 'progress'],
    'radio-group' => ['requires' => [], 'js' => null, 'blade' => 'radio-group'],
    'schedule' => ['requires' => [], 'js' => null, 'blade' => 'schedule'],
    'section-heading' => ['requires' => [], 'js' => null, 'blade' => 'section-heading'],
    'select' => ['requires' => [], 'js' => null, 'blade' => 'select'],
    'side-nav' => ['requires' => [], 'js' => null, 'blade' => 'side-nav'],
    'sign-in' => ['requires' => [], 'js' => null, 'blade' => 'sign-in'],
    'specimen' => ['requires' => [], 'js' => null, 'blade' => 'specimen'],
    'stacked-list' => ['requires' => [], 'js' => null, 'blade' => 'stacked-list'],
    'stat' => ['requires' => [], 'js' => null, 'blade' => 'stat'],
    'table' => ['requires' => [], 'js' => null, 'blade' => 'table'],
    'tabs' => ['requires' => ['badge'], 'js' => null, 'blade' => 'tabs'],
    'textarea' => ['requires' => [], 'js' => null, 'blade' => 'textarea'],
    'toggle' => ['requires' => [], 'js' => null, 'blade' => 'toggle'],
    'vertical-nav' => ['requires' => ['badge'], 'js' => null, 'blade' => 'vertical-nav'],
];
