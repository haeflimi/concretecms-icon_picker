<?php

namespace IconPicker;

/**
 * The icons of the active theme. The theme package binds its implementation in its on_start (the picker can't know
 * which icons a theme supports); without a binding the picker returns the callers' fallbacks.
 *
 * The theme also serves its icons to the n8n workflow: GET /ccm/api/1.0/icons (?all=1 for every icon), answering
 * {"data": [{"name": "…", "description": "…"}, …]}, guarded by the OAuth2 scope apiScope().
 */
interface IconSet
{
    /** The icon exists in the theme and may be rendered */
    public function has(string $name): bool;

    /**
     * @return string[] every icon name of the theme
     */
    public function names(): array;

    /**
     * @return array<string, string> the theme's curated icons for automatic picks, name => what it stands for
     */
    public function curated(): array;

    /** The OAuth2 scope of GET /ccm/api/1.0/icons */
    public function apiScope(): string;

    /** The icon as inline SVG for previews, '' for unknown names */
    public function svg(string $name, int $size = 20): string;
}
