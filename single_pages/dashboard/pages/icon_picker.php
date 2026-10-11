<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var IconPicker\IconSet $set
 * @var bool $defaultSet the theme binds no IconSet, Font Awesome is used
 * @var int $curatedCount
 * @var string $iconsUrl iconset.json
 * @var array<int, array<string, mixed>> $rows IconPickerGuesses, pending first
 * @var string[] $names icon names, curated first
 * @var bool $enabled
 * @var string $url
 * @var string $secret
 * @var int|null $lastRun
 * @var string $tokenName
 * @var string|null $runResult
 * @var string[]|null $runLog
 * @var array{text: string, icon: ?string, reason: string, valid: bool}|null $testResult
 */

use IconPicker\Entity\Guess;

$date = app('date');
$statuses = [
    Guess::STATUS_PENDING => [t('Pending'), 'secondary'],
    Guess::STATUS_AI => [t('AI'), 'info'],
    Guess::STATUS_MANUAL => [t('Manual'), 'success'],
    Guess::STATUS_NONE => [t('No fitting icon'), 'warning'],
];
$pending = count(array_filter($rows, static fn ($r) => $r['status'] === Guess::STATUS_PENDING));
$check = static fn (bool $ok): string => $ok
    ? '<i class="fas fa-check-circle text-success"></i>'
    : '<i class="fas fa-exclamation-triangle text-warning"></i>';
?>
<div id="ip">
    <p class="text-muted" style="max-width: 60rem">
        <?= t('The theme shows icons for texts like the folder names of the club documents. New texts are collected here and the "Pick Icons" task asks an AI workflow in n8n for a fitting icon. The workflow only gets the icons of the set, and every answer is checked again here. Until a text has an icon, the theme shows its own fallback.') ?>
    </p>

    <?php if (isset($testResult)) { ?>
        <div class="alert <?= $testResult['valid'] ? 'alert-success' : 'alert-warning' ?> d-flex align-items-center gap-3">
            <?php if ($testResult['valid']) { ?><span class="ip-preview"><?= $set->html($testResult['icon'], 24) ?></span><?php } ?>
            <div>
                <strong><?= h($testResult['text']) ?> → <?= h($testResult['icon'] ?? '–') ?></strong>
                <?php if (!$testResult['valid']) { ?> (<?= t('not an icon of the set') ?>)<?php } ?>
                <?php if ($testResult['reason'] !== '') { ?><br><span class="small"><?= h($testResult['reason']) ?></span><?php } ?>
                <br><span class="small text-muted"><?= t('Test only, nothing was stored.') ?></span>
            </div>
        </div>
    <?php } ?>
    <?php if (isset($runResult)) { ?>
        <div class="alert alert-info">
            <strong><?= h($runResult) ?></strong>
            <?php if (!empty($runLog)) { ?><ul class="mb-0 mt-2 small"><?php foreach ($runLog as $line) { ?><li><?= h($line) ?></li><?php } ?></ul><?php } ?>
        </div>
    <?php } ?>

    <div class="row g-4 mb-5">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5"><?= t('Settings') ?></h3>
                    <form method="post" action="<?= h($view->action('save_settings')) ?>">
                        <?php $token->output($tokenName) ?>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="ip-enabled" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ip-enabled"><?= t('Pick icons with n8n') ?></label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="ip-url"><?= t('Webhook URL (production)') ?></label>
                            <input type="url" class="form-control" id="ip-url" name="url" value="<?= h($url) ?>" placeholder="https://n8n.example.com/webhook/icon-picker">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="ip-secret"><?= t('Secret') ?></label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="ip-secret" name="secret" value="<?= h($secret) ?>" autocomplete="off" spellcheck="false">
                                <button type="button" class="btn btn-outline-secondary" id="ip-generate"><?= t('Generate') ?></button>
                            </div>
                            <div class="form-text"><?= t('Sent as header %s; n8n checks it.', '<code>X-Icon-Picker-Secret</code>') ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><?= t('Save') ?></button>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5"><?= t('Status') ?></h3>
                    <ul class="list-unstyled mb-3">
                        <li><i class="fas fa-icons text-muted"></i> <?= $defaultSet ? t('Icon set: Font Awesome (default, the theme has no own set)') : t('Icon set: the theme\'s own') ?> – <a href="<?= h($iconsUrl) ?>" target="_blank"><?= t2('%s icon in iconset.json', '%s icons in iconset.json', $curatedCount) ?></a></li>
                        <li><?= $check($url !== '' && $enabled) ?> <?= $url === '' ? t('No webhook URL') : ($enabled ? t('Picking is on') : t('Picking is off')) ?></li>
                        <li><i class="fas fa-clock text-muted"></i> <?= t('Last task run: %s', $lastRun ? $date->formatDateTime($lastRun) : t('never')) ?></li>
                        <li><i class="fas fa-hourglass-half text-muted"></i> <?= t2('%s text pending', '%s texts pending', $pending) ?></li>
                    </ul>
                    <form method="post" action="<?= h($view->action('run')) ?>" class="mb-3">
                        <?php $token->output($tokenName) ?>
                        <button type="submit" class="btn btn-secondary" <?= $pending === 0 ? 'disabled' : '' ?>><i class="fas fa-play"></i> <?= t('Pick pending icons now') ?></button>
                    </form>
                    <form method="post" action="<?= h($view->action('test')) ?>">
                        <?php $token->output($tokenName) ?>
                        <label class="form-label" for="ip-test"><?= t('Test the connection') ?></label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="ip-test" name="text" placeholder="Generalversammlung 2026">
                            <button type="submit" class="btn btn-outline-secondary"><?= t('Test') ?></button>
                        </div>
                        <input type="text" class="form-control form-control-sm" name="context" placeholder="<?= h(t('Optional context, e.g. Folder tab in the club documents; files: Protokoll GV 2025.pdf')) ?>">
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h3 class="h5"><?= t('n8n setup') ?></h3>
                    <ol class="ip-steps">
                        <li>
                            <strong><?= t('Download the workflow') ?></strong> –
                            <?= t('the address of this site\'s icon list is already filled in.') ?><br>
                            <a class="btn btn-sm btn-outline-primary mt-2" href="<?= h($view->action('download_workflow')) ?>"><i class="fas fa-download"></i> icon-picker.n8n.json</a>
                        </li>
                        <li><strong><?= t('Import it in n8n') ?></strong>: <?= t('Workflows → Import from File.') ?></li>
                        <li><strong><?= t('Choose the model') ?></strong>: <?= t('add your model credential (e.g. Anthropic) to the node "Anthropic Chat Model". The model and the prompt can be changed in n8n at any time, without touching the site. Any other chat model node works too.') ?></li>
                        <li><strong><?= t('Secure the webhook') ?></strong>: <?= t('create a "Header Auth" credential for the node "Webhook" with the name %s and the secret from the settings as value.', '<code>X-Icon-Picker-Secret</code>') ?></li>
                        <li><strong><?= t('Activate the workflow') ?></strong>, <?= t('copy the production URL of the node "Webhook" into the settings, switch picking on and save.') ?></li>
                        <li><strong><?= t('Test the connection') ?></strong> <?= t('with a sample text. The "Pick Icons" task then runs with the scheduler (Dashboard › System › Automation); "Pick pending icons now" runs it right away.') ?></li>
                    </ol>
                    <p class="small text-muted mb-0"><?= t('The node "Fetch Icons" reads the icons the model may choose from %s, a file this page and the task keep up to date (no credential needed; n8n must be able to reach this site).', '<code>' . h($iconsUrl) . '</code>') ?></p>
                </div>
            </div>
        </div>
    </div>

    <h3 class="h5"><?= t('Texts and their icons') ?></h3>
    <?php if ($rows === []) { ?>
        <p class="text-muted"><?= t('No texts yet. They show up here when a page with such icons (e.g. the club documents) is viewed.') ?></p>
    <?php } else { ?>
        <datalist id="ip-icons"><?php foreach ($names as $name) { ?><option value="<?= h($name) ?>"><?php } ?></datalist>
        <table class="table align-middle">
            <thead>
                <tr><th><?= t('Text') ?></th><th style="width: 4rem"><?= t('Icon') ?></th><th><?= t('Status') ?></th><th style="width: 26rem"><?= t('Change') ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) {
                    [$statusLabel, $tone] = $statuses[$row['status']] ?? [$row['status'], 'secondary'];
                    $id = (int) $row['id']; ?>
                    <tr>
                        <td>
                            <?= h($row['label']) ?>
                            <?php if ($row['context'] !== '') { ?><div class="small text-muted ip-context" title="<?= h($row['context']) ?>"><?= h($row['context']) ?></div><?php } ?>
                        </td>
                        <td><?php if ($row['icon'] !== null) { ?><span class="ip-preview" title="<?= h($row['icon']) ?>"><?= $set->html($row['icon'], 22) ?></span><?php } ?></td>
                        <td>
                            <span class="badge text-bg-<?= $tone ?>"><?= h($statusLabel) ?></span>
                            <?php if ($row['icon'] !== null) { ?><div class="small"><code><?= h($row['icon']) ?></code></div><?php } ?>
                            <?php if ($row['reason'] !== '') { ?><div class="small text-muted"><?= h($row['reason']) ?></div><?php } ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <form method="post" action="<?= h($view->action('set_icon', $id)) ?>" class="input-group input-group-sm">
                                    <?php $token->output($tokenName) ?>
                                    <input type="text" class="form-control" name="icon" list="ip-icons" value="<?= h($row['icon'] ?? '') ?>" placeholder="<?= h(t('Icon name')) ?>" aria-label="<?= h(t('Icon')) ?>" autocomplete="off" spellcheck="false" required>
                                    <button type="submit" class="btn btn-outline-primary" title="<?= h(t('Set this icon by hand')) ?>"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="<?= h($view->action('ask_again', $id)) ?>">
                                    <?php $token->output($tokenName) ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= h(t('Ask the AI again')) ?>" <?= $row['status'] === Guess::STATUS_PENDING ? 'disabled' : '' ?>><i class="fas fa-redo"></i></button>
                                </form>
                                <form method="post" action="<?= h($view->action('delete_row', $id)) ?>" onsubmit="return confirm(<?= h(json_encode(t('Delete this text?'))) ?>)">
                                    <?php $token->output($tokenName) ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= h(t('Delete')) ?>"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <p class="small text-muted"><?= t('Icons set by hand are never changed by the task. The context is only a hint for the AI: one text gets the same icon everywhere.') ?></p>
    <?php } ?>
</div>

<style>
#ip .ip-preview { display: inline-grid; place-items: center; width: 36px; height: 36px; border: 1px solid var(--bs-border-color); border-radius: 6px; }
#ip .ip-context { max-width: 32rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#ip .ip-steps li { margin-bottom: .75rem; }
#ip .ip-values { display: grid; grid-template-columns: max-content 1fr; gap: .15rem 1rem; margin: .5rem 0 0; font-size: .875rem; }
#ip .ip-values dt { font-weight: normal; color: var(--bs-secondary-color); }
#ip .ip-values dd { margin: 0; overflow-wrap: anywhere; }
</style>
<script>
document.getElementById('ip-generate').addEventListener('click', function () {
    var bytes = new Uint8Array(24);
    crypto.getRandomValues(bytes);
    document.getElementById('ip-secret').value = Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
});
</script>
