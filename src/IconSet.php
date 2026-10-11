<?php

namespace IconPicker;

/**
 * The icons a pick may use. A theme with its own icons binds its implementation in its on_start (the picker can't know
 * which icons a theme supports); without a binding the picker uses FontAwesomeIconSet. The picker publishes curated()
 * as iconset.json for the n8n workflow (IconSetFile), so a theme needs no file or endpoint of its own.
 */
interface IconSet
{
    /** The icon exists in the set and may be rendered */
    public function has(string $name): bool;

    /**
     * @return string[] every icon name of the set
     */
    public function names(): array;

    /**
     * @return array<string, string> the icons an automatic pick may choose from, name => what it stands for (English)
     */
    public function curated(): array;

    /** The icon as HTML for previews (inline SVG, an icon font tag, …), '' for unknown names */
    public function html(string $name, int $size = 20): string;
}
