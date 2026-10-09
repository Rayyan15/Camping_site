<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_resource_and_page_uses_a_navigation_group_registered_in_the_panel(): void
    {
        $panel = Filament::getDefaultPanel();

        $registered = collect($panel->getNavigationGroups())
            ->map(fn (NavigationGroup|string $group) => $group instanceof NavigationGroup ? $group->getLabel() : $group)
            ->all();

        $used = collect([...$panel->getResources(), ...$panel->getPages()])
            ->mapWithKeys(fn (string $class) => [$class => $class::getNavigationGroup()])
            ->filter();

        $this->assertNotEmpty($used);

        foreach ($used as $class => $group) {
            $this->assertContains($group, $registered, "{$class} uses unregistered navigation group '{$group}'.");
        }
    }
}
