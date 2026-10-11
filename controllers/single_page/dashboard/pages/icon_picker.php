<?php

namespace Concrete\Package\IconPicker\Controller\SinglePage\Dashboard\Pages;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Entity\Automation\Task;
use Concrete\Core\Page\Controller\DashboardPageController;
use DateTime;
use IconPicker\Entity\Guess;
use IconPicker\FontAwesomeIconSet;
use IconPicker\IconSetFile;
use IconPicker\N8nClient;
use IconPicker\Picker;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Dashboard > Pages & Themes > Icon Picker: the stored icons (correct, ask again, delete), the n8n settings, a setup
 * guide with the workflow download and a connection test.
 */
class IconPicker extends DashboardPageController
{
    const TOKEN = 'icon_picker';

    /** Placeholder in n8n/icon-picker.n8n.json, filled in by download_workflow() */
    const PLACEHOLDER_ICONS_URL = '__ICONS_URL__';

    public function view()
    {
        $picker = $this->app->make(Picker::class);
        $client = $this->app->make(N8nClient::class);
        $config = $this->app->make('config');
        $set = $picker->getIconSet();
        $db = $this->app->make(Connection::class);
        $file = $this->app->make(IconSetFile::class);
        try {
            $file->write($set);
        } catch (RuntimeException $e) {
            $this->error->add($e->getMessage());
        }

        $this->set('set', $set);
        $this->set('defaultSet', $set instanceof FontAwesomeIconSet);
        $this->set('curatedCount', count($set->curated()));
        $this->set('iconsUrl', $file->url());
        $this->set('rows', $db->fetchAllAssociative('SELECT * FROM ' . Picker::TABLE . ' ORDER BY status = ? DESC, label', [Guess::STATUS_PENDING]));
        $this->set('names', array_merge(array_keys($set->curated()), array_diff($set->names(), array_keys($set->curated()))));
        $this->set('enabled', (bool) $config->get('icon_picker::n8n.enabled'));
        $this->set('url', $client->getUrl());
        $this->set('secret', $client->getSecret());
        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['handle' => 'pick_icons']);
        $this->set('lastRun', $task && $task->getDateLastStarted() ? (int) $task->getDateLastStarted() : null);
        $this->set('tokenName', self::TOKEN);
    }

    public function save_settings()
    {
        if (!$this->validate()) {
            return $this->view();
        }
        $url = trim((string) $this->request->request->get('url'));
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error->add(t('The webhook URL is not a valid URL.'));

            return $this->view();
        }
        $config = $this->app->make('config');
        $config->save('icon_picker::n8n.enabled', (bool) $this->request->request->get('enabled'));
        $config->save('icon_picker::n8n.url', $url);
        $config->save('icon_picker::n8n.secret', trim((string) $this->request->request->get('secret')));
        $this->flash('success', t('Settings saved.'));

        return $this->buildRedirect($this->action());
    }

    /**
     * Sets the icon of a row by hand; the task never changes it again.
     */
    public function set_icon($id = null)
    {
        if (!$this->validate()) {
            return $this->view();
        }
        $icon = trim((string) $this->request->request->get('icon'));
        $set = $this->app->make(Picker::class)->getIconSet();
        if (!$set->has($icon)) {
            $this->error->add(t('"%s" is not an icon of the set.', $icon));

            return $this->view();
        }
        $this->updateRow($id, ['icon' => $icon, 'status' => Guess::STATUS_MANUAL, 'reason' => '']);
        $this->flash('success', t('Icon saved.'));

        return $this->buildRedirect($this->action());
    }

    public function ask_again($id = null)
    {
        if (!$this->validate()) {
            return $this->view();
        }
        $this->updateRow($id, ['icon' => null, 'status' => Guess::STATUS_PENDING, 'reason' => '']);
        $this->flash('success', t('The text will be asked again on the next run.'));

        return $this->buildRedirect($this->action());
    }

    public function delete_row($id = null)
    {
        if (!$this->validate()) {
            return $this->view();
        }
        $this->app->make(Connection::class)->delete(Picker::TABLE, ['id' => (int) $id]);
        $this->app->make(Picker::class)->flushPageCache();
        $this->flash('success', t('Deleted. The text comes back as pending when a page shows it again.'));

        return $this->buildRedirect($this->action());
    }

    /**
     * Runs what the "Pick Icons" task does, right away.
     */
    public function run()
    {
        if (!$this->validate()) {
            return $this->view();
        }
        $log = [];
        try {
            $counts = $this->app->make(Picker::class)->pickPending(function (string $line) use (&$log) {
                $log[] = $line;
            });
            $this->set('runResult', t('Icons picked: %s, without a fitting icon: %s, failed (will retry): %s.', $counts['picked'], $counts['none'], $counts['failed']));
        } catch (RuntimeException $e) {
            $this->error->add($e->getMessage());
        }
        $this->set('runLog', $log);

        return $this->view();
    }

    /**
     * Asks n8n for a sample text without storing anything.
     */
    public function test()
    {
        if (!$this->validate()) {
            return $this->view();
        }
        $text = trim((string) $this->request->request->get('text')) ?: 'Generalversammlung 2026';
        $client = $this->app->make(N8nClient::class);
        if (!$client->isConfigured()) {
            $this->error->add(t('Enter the webhook URL first.'));

            return $this->view();
        }
        try {
            $answer = $client->ask($text, trim((string) $this->request->request->get('context')));
            $set = $this->app->make(Picker::class)->getIconSet();
            $this->set('testResult', $answer + ['text' => $text, 'valid' => $answer['icon'] !== null && $set->has($answer['icon'])]);
        } catch (RuntimeException $e) {
            $this->error->add($e->getMessage());
        }

        return $this->view();
    }

    /**
     * The shipped workflow with the URL of this site's iconset.json filled in.
     */
    public function download_workflow()
    {
        $json = (string) file_get_contents(dirname(__DIR__, 4) . '/n8n/icon-picker.n8n.json');
        $json = str_replace(self::PLACEHOLDER_ICONS_URL, $this->app->make(IconSetFile::class)->url(), $json);

        return new Response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="icon-picker.n8n.json"',
        ]);
    }

    protected function validate(): bool
    {
        if (!$this->token->validate(self::TOKEN)) {
            $this->error->add($this->token->getErrorMessage());

            return false;
        }

        return true;
    }

    protected function updateRow($id, array $data): void
    {
        $data['updatedAt'] = (new DateTime())->format('Y-m-d H:i:s');
        $this->app->make(Connection::class)->update(Picker::TABLE, $data, ['id' => (int) $id]);
        $this->app->make(Picker::class)->flushPageCache();
    }
}
