# Content Audit for Craft CMS

Audit your Craft CMS 5 content for common issues. Currently detects:

- **Orphaned Assets** — files uploaded to a volume that aren't referenced by any entry
- **Missing Alt Text** — images that have no alt text set
- **Large Assets** — files over 2 MB (warning) or 5 MB (critical); threshold configurable via `config/content-audit.php`
- **Broken References** — entries whose relational fields point to disabled or deleted elements

Results are stored in the database and persist between page loads. Each issue links directly to the offending element in the Control Panel.

---

## Installation

### Via Composer (local dev)

1. Clone this repo alongside your Craft project.

2. In your Craft project's `composer.json`, add the repository and require the plugin:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../craft-content-audit"
        }
    ],
    "require": {
        "iistudio/craft-content-audit": "*"
    }
}
```

3. Run:

```bash
composer update iistudio/craft-content-audit
php craft plugin/install content-audit
```

4. The **Content Audit** item will appear in your CP sidebar.

### Via Plugin Store (once published)

Search for "Content Audit" in the Craft Plugin Store and click Install.

---

## Usage

1. Go to **Content Audit** in the Control Panel sidebar.
2. Click **Run Audit**.
3. Review the results table. Each issue links directly to the offending element so you can fix or delete it.

---

## Extending

Adding a new auditor is straightforward:

1. Create a class in `src/auditors/` implementing `AuditorInterface`:

```php
class MyNewAuditor implements AuditorInterface
{
    public function handle(): string { return 'my-check'; }
    public function label(): string  { return 'My Check'; }
    public function run(): array     { /* return AuditIssue[] */ }
}
```

2. Register it in `AuditService::init()`:

```php
$this->auditors = [
    new OrphanedAssetsAuditor(),
    new MyNewAuditor(),   // <- add here
];
```

That's it — the CP table picks it up automatically.

---

## Plugin Store Submission Checklist

- [x] Hosted on GitHub (public repo)
- [x] `CHANGELOG.md` kept up to date
- [x] Icon at `icon.svg` (square SVG, ideally 150×150)
- [x] `composer.json` `extra.craftcms.plugin` fields complete
- [x] Developer account at [id.craftcms.com](https://id.craftcms.com)
- [x] Plugin submitted at [plugins.craftcms.com/new](https://plugins.craftcms.com/new)

Craft Plugin Store review typically takes a few business days.

---

## Craft 5 Development Notes

**Controllers:** Do not use `requireCpAccess()` — it doesn't exist in Craft 5. Use this pattern instead:

```php
protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

public function actionIndex(): Response
{
    $this->requireLogin();
    // ...
}
```

**Twig templates:** Never put double quotes inside a double-quoted string. If the string contains `"quotes"`, use single quotes for the outer string:

```twig
{# Wrong — breaks Twig parser #}
{{ "Click "Run" to start"|t('plugin') }}

{# Correct #}
{{ 'Click "Run" to start'|t('plugin') }}
```

---

## Requirements

- Craft CMS 5.0+
- PHP 8.2+

## License

Craft — see [LICENSE.md](LICENSE.md)
