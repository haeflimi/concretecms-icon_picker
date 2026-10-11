<?php

namespace IconPicker;

use Concrete\Core\Application\Application;
use Concrete\Core\Cache\Page\PageCache;
use Concrete\Core\Database\Connection\Connection;
use DateTime;
use IconPicker\Entity\Guess;
use RuntimeException;

/**
 * Icons for texts (folder names, titles, …). pick() only reads what is stored: an unknown text is queued as pending
 * and the caller's fallback shows. The "Pick icons" task (pickPending) asks n8n for the pending texts; every icon is
 * checked against the theme's IconSet, so only icons the theme supports are ever returned. One row per text: the
 * same text gets the same icon everywhere, the context is only a hint for the pick.
 */
class Picker
{
    const TABLE = 'IconPickerGuesses';

    /** @var Application */
    protected $app;

    /** @var Connection */
    protected $db;

    /** @var array<string, array{icon: ?string, status: string, context: string}>|null normalized text => row */
    protected $rows;

    public function __construct(Application $app, Connection $db)
    {
        $this->app = $app;
        $this->db = $db;
    }

    public function getIconSet(): ?IconSet
    {
        return $this->app->bound(IconSet::class) ? $this->app->make(IconSet::class) : null;
    }

    /**
     * The icon stored for $text, else $fallback (and $text is queued for the task).
     */
    public function pick(string $text, ?string $fallback = null, string $context = ''): ?string
    {
        $set = $this->getIconSet();
        $key = static::normalize($text);
        if ($set === null || $key === '') {
            return $fallback;
        }
        if ($this->rows === null) {
            $this->rows = [];
            foreach ($this->db->fetchAllAssociative('SELECT text, icon, status, context FROM ' . self::TABLE) as $row) {
                $this->rows[$row['text']] = $row;
            }
        }
        $context = static::clip($context, 500);
        $row = $this->rows[$key] ?? null;
        if ($row === null) {
            $this->db->executeStatement(
                'INSERT IGNORE INTO ' . self::TABLE . ' (text, label, context, icon, status, reason, updatedAt) VALUES (?, ?, ?, NULL, ?, \'\', ?)',
                [$key, static::clip(static::clean($text), 255), $context, Guess::STATUS_PENDING, (new DateTime())->format('Y-m-d H:i:s')]
            );
            $this->rows[$key] = ['icon' => null, 'status' => Guess::STATUS_PENDING, 'context' => $context];

            return $fallback;
        }
        if ($row['status'] === Guess::STATUS_PENDING && $context !== '' && $context !== $row['context']) {
            $this->db->update(self::TABLE, ['context' => $context], ['text' => $key]);
            $this->rows[$key]['context'] = $context;
        }

        return $row['icon'] !== null && $set->has($row['icon']) ? $row['icon'] : $fallback;
    }

    /**
     * Asks n8n for every pending text. Valid answers become "ai", answers that aren't an icon of the theme "none";
     * texts n8n couldn't answer stay pending for the next run.
     *
     * @param callable(string): void $log
     *
     * @throws RuntimeException when n8n isn't set up
     *
     * @return array{picked: int, none: int, failed: int}
     */
    public function pickPending(callable $log): array
    {
        $client = $this->app->make(N8nClient::class);
        $set = $this->getIconSet();
        if ($set === null) {
            throw new RuntimeException(t('The active theme provides no icons for the icon picker.'));
        }
        if (!$client->isEnabled()) {
            throw new RuntimeException(t('The icon picker is switched off or has no n8n webhook URL (Dashboard > Pages & Themes > Icon Picker).'));
        }
        $counts = ['picked' => 0, 'none' => 0, 'failed' => 0];
        $pending = $this->db->fetchAllAssociative('SELECT id, label, context FROM ' . self::TABLE . ' WHERE status = ? ORDER BY id', [Guess::STATUS_PENDING]);
        foreach ($pending as $row) {
            try {
                $answer = $client->ask($row['label'], $row['context']);
            } catch (RuntimeException $e) {
                ++$counts['failed'];
                $log(t('%s: %s', $row['label'], $e->getMessage()));
                // n8n is down or misconfigured: the remaining texts would fail the same way
                if ($counts['picked'] + $counts['none'] === 0 && $counts['failed'] >= 3) {
                    break;
                }
                continue;
            }
            $valid = $answer['icon'] !== null && $set->has($answer['icon']);
            $this->db->update(self::TABLE, [
                'icon' => $valid ? $answer['icon'] : null,
                'status' => $valid ? Guess::STATUS_AI : Guess::STATUS_NONE,
                'reason' => $answer['reason'],
                'updatedAt' => (new DateTime())->format('Y-m-d H:i:s'),
            ], ['id' => $row['id'], 'status' => Guess::STATUS_PENDING]);
            ++$counts[$valid ? 'picked' : 'none'];
            $log($valid ? t('%s → %s', $row['label'], $answer['icon']) : t('%s: no icon of the theme (%s)', $row['label'], $answer['icon'] ?? '-'));
        }
        if ($counts['picked'] > 0) {
            $this->flushPageCache();
        }
        $this->rows = null;

        return $counts;
    }

    /**
     * Pages cached with the fallback icons show the picked ones after this.
     */
    public function flushPageCache(): void
    {
        PageCache::getLibrary()->flush();
    }

    /**
     * The key of a text: entities decoded, tags stripped, whitespace collapsed, lower case.
     */
    public static function normalize(string $text): string
    {
        return static::clip(mb_strtolower(static::clean($text)), 255);
    }

    protected static function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    protected static function clip(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }
}
