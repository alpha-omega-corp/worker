<?php

namespace App\Support;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ComponentAttributeBag;

/**
 * `@field(...)`: the one way a page writes a form field, drawn by the kit's
 * field in one of its three kinds — a line, `type: 'long'`, or `options:` —
 * so every form of every site made from this template reads alike.
 *
 * **The arguments are named, and only these**: PHP refuses an argument this
 * does not declare, so a misspelt `lable:` stops the page being built rather
 * than drawing a field with no label; the kit's field refuses the rest (no
 * label, a type outside its kinds). What a form needs beyond these is a
 * field the kit has not drawn yet, and belongs in the kit, not in one page.
 */
final class Field
{
    /**
     * @param  array<array-key, string>|null  $options
     */
    public static function render(
        string $name,
        string $label,
        string $type = 'text',
        ?array $options = null,
        string|int|float|null $value = null,
        ?string $hint = null,
        ?string $id = null,
        ?string $choose = null,
        ?int $rows = null,
        bool $required = false,
        ?string $autocomplete = null,
        ?string $placeholder = null,
        ?string $inputmode = null,
        ?string $pattern = null,
        ?int $maxlength = null,
        string|int|float|null $min = null,
        string|int|float|null $max = null,
        string|int|float|null $step = null,
        ?string $class = null,
    ): string {
        $attributes = array_filter(get_defined_vars(), fn (mixed $value): bool => $value !== null && $value !== false);

        return Blade::render('<x-kit.field :attributes="$field" />', ['field' => new ComponentAttributeBag($attributes)]);
    }
}
