<?php

namespace iistudio\contentaudit\auditors;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use iistudio\contentaudit\models\AuditIssue;

/**
 * Detects assets that have no alt text set.
 *
 * Loads canonical assets via the element API and checks $asset->alt in PHP.
 * This approach handles Craft 5's content architecture where the native alt
 * field value may not live in the craft_assets table column directly.
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

        // Load all canonical, non-deleted asset IDs.
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
            // Check via the PHP property — handles any storage location in Craft 5.
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
