<?php

namespace App\Console\Commands;

use App\Support\UiKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Everything ui:import and ui:layout accept: the kit's components with what each
 * one requires, and the layouts with their regions. --json is what deployer's
 * uikit MCP server reads.
 */
#[Signature('ui:list {--json : Print it as JSON}')]
#[Description('List the kit components, what each requires, and the layouts with their regions')]
class UiListCommand extends Command
{
    public function handle(UiKit $kit): int
    {
        $components = $kit->components();
        $layouts = $kit->layouts();

        if ($this->option('json')) {
            $this->line((string) json_encode(['components' => $components, 'layouts' => $layouts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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

        return self::SUCCESS;
    }
}
