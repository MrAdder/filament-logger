<?php

use Filament\Schemas\Schema;
use MrAdder\FilamentLogger\Resources\ActivityResource;

/**
 * Guards against a naming collision with Filament's own schema-resolution
 * convention.
 *
 * Filament's InteractsWithSchemas trait treats any protected/public method
 * literally named "default" + ucfirst($schemaName) as the default-value hook
 * for a schema of that name — matched purely by string, with no check of what
 * the method actually does. Page::headerWidgets(Schema $schema): Schema makes
 * "headerWidgets" a real schema name, so a page method once named
 * defaultHeaderWidgets() got hijacked: Filament called it expecting a Schema
 * back, got our array of widget classes instead, and fed that array into
 * Page::headerWidgets(), which threw a TypeError.
 *
 * {{ $this->headerWidgets }} in Filament's own page Blade view is what
 * triggers this — getSchema() reproduces exactly that access without needing
 * the full page to render, which is otherwise fragile in a Testbench harness.
 * Calling getHeaderWidgets() directly, as other tests in this suite do to
 * cover its contents, never exercises this path at all, which is how the
 * collision shipped unnoticed.
 */
it('resolves every schema Filament looks for on the activity list page without a naming collision', function () {
    $pageClass = (new ReflectionMethod(ActivityResource::class, 'getListActivitiesPage'))->invoke(null);

    $page = new $pageClass;

    foreach (['headerWidgets', 'footerWidgets'] as $schemaName) {
        expect($page->getSchema($schemaName))
            ->not->toBeNull("Resolving the '{$schemaName}' schema returned null unexpectedly.");
    }
})->skip(
    ! class_exists(Schema::class),
    'Filament 3 has no Schema system, so this collision class cannot occur there.',
);
