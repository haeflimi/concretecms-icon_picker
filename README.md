# Icon Picker

Concrete CMS package that picks theme icons for short texts such as folder names or tab titles. An AI workflow
in [n8n](https://n8n.io) does the picking. The model and the prompt live in n8n, so you can change them without
touching the site.

## How it works

```
template ─ Icons::guess($label, $fallback, $context)
             └─ IconPicker\Picker::pick()   stored icon, or the fallback + the text is queued as "pending"

Task "Pick Icons" (scheduler / dashboard)
  └─ POST {text, context} ──► n8n webhook
                               ├─ GET /ccm/api/1.0/icons   (theme's icons, OAuth2 client credentials)
                               ├─ chat model picks one
                               └─ {icon, confidence, reason}
  └─ IconSet::has($icon)? → stored as "ai", otherwise "none" (fallback stays)
```

- **The theme owns the icons.** It binds an `IconPicker\IconSet` in its `on_start()` and serves
  `GET /ccm/api/1.0/icons` (`?all=1` for every icon) inside Concrete's REST API, guarded by its own OAuth2 scope.
  The picker only stores and returns icons that `IconSet::has()` accepts. Without a theme binding, every call
  returns the caller's fallback.
- **One row per text.** The same text gets the same icon everywhere. The context (where the text shows, a few
  file names) only helps with vague labels. It is updated while the text is pending, and a change in context
  never re-picks an icon that's already chosen.
- **No request to n8n while a page renders.** Picking happens only in the task.
- Statuses: `pending` (waiting for the task), `ai` (picked by n8n), `manual` (set on the dashboard, never
  changed by the task), `none` (n8n gave no icon of the theme; the fallback shows).

## Setup

Everything is also explained on **Dashboard › Pages & Themes › Icon Picker**.

1. **Download the workflow** from the dashboard page. The site URL and the theme's API scope are already filled
   in. The raw file is `n8n/icon-picker.n8n.json`; replace `__SITE_URL__` and `__SCOPE__` in it yourself if you
   use that one.
2. In n8n: **Workflows → Import from File**.
3. **API integration**: enable the REST API (Dashboard › System › API › Settings), then create an integration under
   Integrations with custom scopes containing only the theme's scope (`theme_blackout:icons:read` for Blackout). In
   n8n, give the node **Fetch Icons** an *OAuth2 API* credential:
   - Grant Type: Client Credentials
   - Access Token URL: `https://<site>/oauth/2.0/token`
   - Client ID / Secret: from the integration
   - Scope: the theme's scope
   - Authentication: Body
4. **Model**: add your model credential to **Anthropic Chat Model** (default `claude-haiku-5-5`, temperature 0),
   or replace it with any other chat model node.
5. **Webhook**: give the node **Webhook** a *Header Auth* credential named `X-Icon-Picker-Secret`, with the secret
   from the dashboard settings as its value.
6. Activate the workflow, paste its **production** webhook URL into the dashboard settings, switch picking on and
   save. Use **Test the connection** to check.

The task **Pick Icons** (`concrete/bin/concrete task:pick-icons`) runs with the scheduler
(`concrete:scheduler:run` from cron). **Pick pending icons now** on the dashboard runs it right away.

## For theme developers

A theme supports the picker by:

- implementing `IconPicker\IconSet` (`has`, `names`, `curated`, `apiScope`, `svg`) and binding it in `on_start()`
  when `interface_exists(\IconPicker\IconSet::class)`;
- serving `GET /ccm/api/1.0/icons` in the core API format `{"data": [{"name": "…", "description": "…"}, …]}`,
  guarded by `apiScope()`;
- calling `$app->make(\IconPicker\Picker::class)->pick($text, $fallback, $context)` when the picker is bound.

`theme_blackout` is the reference (`src/PickerIconSet.php`, `src/Api/`, `config/icon_palette.php`, `Icons::guess()`).
