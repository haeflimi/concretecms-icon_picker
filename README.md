# Icon Picker

Concrete CMS package that picks icons for short texts such as folder names or tab titles. An AI workflow
in [n8n](https://n8n.io) does the picking. The model and the prompt live in n8n, so you can change them without
touching the site.

## How it works

```
template ─ $picker->pick($label, $fallback, $context)
             └─ stored icon, or the fallback + the text is queued as "pending"

Task "Pick Icons" (scheduler / dashboard)
  ├─ writes application/files/icon_picker/iconset.json   (curated icons of the set)
  └─ POST {text, context} ──► n8n webhook
                               ├─ GET …/iconset.json      (static file, no credential)
                               ├─ chat model picks one
                               └─ {icon, confidence, reason}
  └─ IconSet::has($icon)? → stored as "ai", otherwise "none" (fallback stays)
```

- **The icon set decides what may be picked.** A theme with its own icons binds an `IconPicker\IconSet` in its
  `on_start()`. Without one, the picker uses **Font Awesome 5 Free**, which every Concrete 9 site loads (names and
  descriptions from the metadata core ships). The picker only stores and returns icons that `IconSet::has()` accepts.
- **`iconset.json`** is the set's curated icons as `{"name": "what it stands for", …}`. The picker writes it itself
  (task run and dashboard page, only when it changed), so a theme needs no file or endpoint of its own.
- **One row per text.** The same text gets the same icon everywhere. The context (where the text shows, a few
  file names) only helps with vague labels. It is updated while the text is pending, and a change in context
  never re-picks an icon that's already chosen.
- **No request to n8n while a page renders.** Picking happens only in the task.
- Statuses: `pending` (waiting for the task), `ai` (picked by n8n), `manual` (set on the dashboard, never
  changed by the task), `none` (n8n gave no icon of the theme; the fallback shows).

## Setup

Everything is also explained on **Dashboard › Pages & Themes › Icon Picker**.

1. **Download the workflow** from the dashboard page. The URL of the site's `iconset.json` is already filled in. The
   raw file is `n8n/icon-picker.n8n.json`; replace `__ICONS_URL__` in it yourself if you use that one. n8n must be
   able to reach the site, because the node **Fetch Icons** reads the icons from it (no credential needed).
2. In n8n: **Workflows → Import from File**.
3. **Model**: add your model credential to **Anthropic Chat Model** (default `claude-haiku-5-5`, temperature 0),
   or replace it with any other chat model node.
4. **Webhook**: give the node **Webhook** a *Header Auth* credential named `X-Icon-Picker-Secret`, with the secret
   from the dashboard settings as its value.
5. Activate the workflow, paste its **production** webhook URL into the dashboard settings, switch picking on and
   save. Use **Test the connection** to check.

The task **Pick Icons** (`concrete/bin/concrete task:pick-icons`) runs with the scheduler
(`concrete:scheduler:run` from cron). **Pick pending icons now** on the dashboard runs it right away.

## Icon sets

**Themes with their own icons** implement `IconPicker\IconSet` and bind it in `on_start()` when
`interface_exists(\IconPicker\IconSet::class)`:

- `has($name)`, `names()`: the icons that may be rendered;
- `curated()`: `name => short English description`, the icons a pick may choose from (written to `iconset.json`);
- `html($name, $size)`: markup for the dashboard previews.

`theme_blackout` is the reference (`src/PickerIconSet.php`, `config/icon_palette.php`, `Icons::guess()`).

**Other themes** (Atomik, …) get Font Awesome. Adjust the curated icons with the config key
`icon_picker::fontawesome.curated` (defaults in `config/fontawesome.php`, override in
`application/config/icon_picker/fontawesome.php`). A template asks for an icon like this:

```php
<?php $picker = app(\IconPicker\Picker::class); ?>
<i class="fas fa-<?= h($picker->pick($folder->getName(), 'folder', 'Folder in the downloads')) ?>"></i>
```
