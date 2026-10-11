<?php

namespace IconPicker;

use Concrete\Core\Application\Application;
use Symfony\Component\Yaml\Yaml;

/**
 * The default icon set for themes that don't bind their own IconSet: Font Awesome 5 Free, which every Concrete 9
 * site loads (Bedrock). Names and descriptions come from the metadata core ships with it; brand logos are left out.
 * The curated part is config/fontawesome.php (icon_picker::fontawesome.curated).
 */
class FontAwesomeIconSet implements IconSet
{
    const METADATA = DIR_BASE_CORE . '/bedrock/assets/@fortawesome/fontawesome-free/metadata/icons.yml';

    /** @var Application */
    protected $app;

    /** @var array<string, array{label: string, terms: string[], solid: bool}>|null */
    protected static $icons;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function has(string $name): bool
    {
        return isset($this->icons()[$name]);
    }

    public function names(): array
    {
        return array_keys($this->icons());
    }

    public function curated(): array
    {
        $icons = $this->icons();
        $curated = [];
        foreach ((array) $this->app->make('config')->get('icon_picker::fontawesome.curated') as $name) {
            if (isset($icons[$name])) {
                $terms = array_slice($icons[$name]['terms'], 0, 8);
                $curated[$name] = $icons[$name]['label'] . ($terms ? ': ' . implode(', ', $terms) : '');
            }
        }

        return $curated;
    }

    public function html(string $name, int $size = 20): string
    {
        $icon = $this->icons()[$name] ?? null;
        if ($icon === null) {
            return '';
        }

        return '<i class="' . ($icon['solid'] ? 'fas' : 'far') . ' fa-' . h($name) . '" style="font-size: ' . $size . 'px" aria-hidden="true"></i>';
    }

    /**
     * The parsed metadata, cached (the YAML file is ~300 KB and has() runs on every page that shows picked icons).
     *
     * @return array<string, array{label: string, terms: string[], solid: bool}>
     */
    protected function icons(): array
    {
        if (static::$icons === null) {
            $item = $this->app->make('cache/expensive')->getItem('icon_picker/fontawesome/' . (int) @filemtime(self::METADATA));
            if ($item->isMiss()) {
                $icons = [];
                $data = is_file(self::METADATA) ? (array) Yaml::parseFile(self::METADATA) : [];
                foreach ($data as $name => $meta) {
                    $styles = (array) ($meta['styles'] ?? []);
                    if (array_diff($styles, ['brands']) === []) {
                        continue;
                    }
                    $icons[(string) $name] = [
                        'label' => (string) ($meta['label'] ?? $name),
                        'terms' => array_map('strval', (array) ($meta['search']['terms'] ?? [])),
                        'solid' => in_array('solid', $styles, true),
                    ];
                }
                $item->set($icons)->save();
            }
            static::$icons = (array) $item->get();
        }

        return static::$icons;
    }
}
