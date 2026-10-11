<?php

namespace IconPicker;

use Concrete\Core\Application\Application;
use RuntimeException;

/**
 * application/files/icon_picker/iconset.json: the curated icons of the active IconSet as {"name": "description", …},
 * a static file the n8n workflow reads (no endpoint, no credential). Written by the task and the dashboard page, so it
 * follows changes to the theme or the curated list.
 */
class IconSetFile
{
    const PATH = '/icon_picker/iconset.json';

    /** @var Application */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @throws RuntimeException when the file can't be written
     */
    public function write(IconSet $set): void
    {
        $json = json_encode((object) $set->curated(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $file = DIR_FILES_UPLOADED_STANDARD . self::PATH;
        if (is_file($file) && file_get_contents($file) === $json) {
            return;
        }
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        if (@file_put_contents($file, $json) === false) {
            throw new RuntimeException(t('The icon list %s could not be written.', $file));
        }
    }

    public function url(): string
    {
        return rtrim((string) $this->app->make('url/canonical'), '/') . REL_DIR_FILES_UPLOADED_STANDARD . self::PATH;
    }
}
