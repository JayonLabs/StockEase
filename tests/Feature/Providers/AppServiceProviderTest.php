<?php

use Carbon\Carbon;

it('sets carbon locale to indonesian on boot', function () {
    expect(Carbon::getLocale())->toBe('id');
});

it('carbon uses indonesian for diffForHumans', function () {
    $date = Carbon::now()->subDay();

    expect($date->diffForHumans())->toContain('hari');
});

it('carbon uses indonesian for isoFormat day names', function () {
    $monday = Carbon::now()->startOfWeek();

    expect($monday->isoFormat('ddd'))->toBe('Sen');
});

it('carbon uses indonesian for translatedFormat', function () {
    $date = Carbon::now();

    expect($date->translatedFormat('F'))->toBeString()
        ->not->toBeEmpty();
});
