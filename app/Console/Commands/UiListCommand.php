<?php

namespace App\Console\Commands;

use AlphaOmega\Site\Prefabs\Prefabs;
use App\Support\UiKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Everything ui:import and ui:layout accept: the kit's components with what each
 * one requires, the layouts with their regions, and the palettes and directions
 * a page is drawn in. --json is what deployer's uikit MCP server reads.
 *
 * In the JSON, `components` is the themed kit alone, the only components
 * deployer's mockups place; the raw reference library, which ui:import and an
 * app layout still take, is `references`. `prefabs` is each prefab the base
 * binds with the kit components it draws, read off the base, so deployer has
 * no second list of them to fall behind.
 */
#[Signature('ui:list {--json : Print it as JSON}')]
#[Description('List the kit components, what each requires, the layouts with their regions, and the palettes and directions')]
class UiListCommand extends Command
{
    public function handle(UiKit $kit): int
    {
        $components = $kit->components();
        $layouts = $kit->layouts();
        $palettes = $kit->palettes();
        $directions = $kit->directions();

        if ($this->option('json')) {
            $kits = array_filter($components, fn (string $name): bool => $kit->isKit($name), ARRAY_FILTER_USE_KEY);

            $this->line((string) json_encode([
                'components' => $kits,
                'references' => array_diff_key($components, $kits),
                'layouts' => $layouts,
                'palettes' => $palettes,
                'directions' => $directions,
                'prefabs' => (object) self::prefabs(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Component', 'Requires', 'JS'], array_map(
            fn (string $name, array $component): array => [$name, implode(', ', $component['requires']), $component['js'] ?? ''],
            array_keys($components),
            $components,
        ));

        $this->table(['Layout', 'Regions', 'Summary'], array_map(
            fn (string $name, array $layout): array => [$name, implode(', ', $layout['regions']), $layout['summary']],
            array_keys($layouts),
            $layouts,
        ));

        $this->components->twoColumnDetail('Palettes', implode(', ', $palettes));
        $this->components->twoColumnDetail('Directions', implode(', ', $directions) ?: 'none: this kit.css is older than them');

        return self::SUCCESS;
    }

    /**
     * Each prefab the base binds, with the kit components it draws.
     *
     * @return array<string, list<string>>
     */
    private static function prefabs(): array
    {
        $prefabs = app(Prefabs::class);

        return collect($prefabs->names())
            ->mapWithKeys(fn (string $name): array => [$name => array_keys($prefabs->find($name)?->presenters() ?? [])])
            ->all();
    }
}
