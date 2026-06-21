<?php

namespace kooba\contentaudit\auditors;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use kooba\contentaudit\models\AuditIssue;

/**
 * Detects assets that have no alt text set.
 *
 * Uses the element query API to load assets and checks $asset->alt via
 * PHP rather than a raw SQL WHERE clause. This avoids issues with Craft 5's
 * content architecture where native field values may not live in the column
 * we'd expect in craft_assets.
 */
class MissingAltTextAuditor implements AuditorInterface
{
    public function handle(): string
    {
        return 'missing-alt-text';
    }

    public function label(): string
    {
        return 'Missing Alt Text';
    }

    public function columns(): array
    {
        return [
            ['key' => 'filename', 'label' => 'File'],
            ['key' => 'volume',   'label' => 'Volume'],
            ['key' => 'size',     'label' => 'Size'],
            ['key' => 'uploaded', 'label' => 'Uploaded'],
        ];
    }

    public function run(): array
    {
        $issues = [];

        // Load all canonical, non-deleted assets via the element API.
        // Filtering in PHP avoids SQL column assumptions about where Craft 5
        // stores the native alt field value.
        $canonicalIds = (new Query())
            ->select(['e.id'])
            ->from(['e' => '{{%elements}}'])
            ->innerJoin(['a' => '{{%assets}}'], '[[a.id]] = [[e.id]]')
            ->where([
                'e.dateDeleted' => null,
                'e.canonicalId' => null,
            ])
            ->column();

        if (empty($canonicalIds)) {
            return [];
        }

        /** @var Asset[] $assets */
        $assets = Asset::find()
            ->id($canonicalIds)
            ->status(null)
            ->all();

        foreach ($assets as $asset) {
            // Check the live PHP property — handles any storage location.
            if (trim((string) ($asset->alt ?? '')) !== '') {
                continue;
            }

            $issue = new AuditIssue();
            $issue->auditor   = $this->handle();
            $issue->severity  = 'warning';
            $issue->elementId = $asset->id;
            $issue->message   = sprintf('"%s" has no alt text.', $asset->filename);
            $issue->cpEditUrl = $asset->cpEditUrl;
            $issue->context   = [
                'filename' => $asset->filename,
                'volume'   => $asset->volume?->name ?? '—',
                'size'     => Craft::$app->getFormatter()->asShortSize($asset->size),
                'uploaded' => $asset->dateCreated?->format('Y-m-d') ?? '—',
                'thumbUrl' => $asset->kind === 'image' ? Craft::$app->getAssets()->getThumbUrl($asset, 64, 64) : null,
            ];
            $issues[] = $issue;
        }

        return $issues;
    }
}
