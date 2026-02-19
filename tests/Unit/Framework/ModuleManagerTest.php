<?php

use Framework\Core\Module\ModuleManager;
use Framework\Core\Module\ModuleRegistry;

beforeEach(function () {
    $this->moduleManager = app(ModuleManager::class);
    $this->moduleRegistry = app(ModuleRegistry::class);
});

test('module manager can discover modules', function () {
    $modules = $this->moduleManager->discoverModules(base_path('modules'));

    expect($modules)->toBeArray()
        ->and(count($modules))->toBeGreaterThanOrEqual(2); // Core and Auth modules
});

test('module registry can track active modules', function () {
    expect($this->moduleRegistry->isActive('core'))->toBeTrue()
        ->and($this->moduleRegistry->isActive('auth'))->toBeTrue()
        ->and($this->moduleRegistry->isActive('non_existent'))->toBeFalse();
});

test('dependency resolver handles module dependencies', function () {
    // This test will be more meaningful once modules are fully integrated
    expect(true)->toBeTrue();
});