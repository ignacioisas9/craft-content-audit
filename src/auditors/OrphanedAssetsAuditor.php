<?php

namespace iistudio\contentaudit\auditors;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use iistudio\contentaudit\models\AuditIssue;

/**
 * Detects assets that are uploaded to Craft but not referenced
 * in any entry via an Assets field (i.e. not in craft_relations).
 *
 * Note: This check uses the relations table, which covers Assets fields,
 * Matrix blocks, Neo blocks, and SuperTable. It does NOT detect assets
 * embedded as raw URLs inside Redactor/CKEditor HTML — that requires
 * HTML parsing and is deferred to a future auditor.
 */
class OrphanedAssetsAuditor implements AuditorInterface
{
    public function handle(): string
    {
        return 'orphaned-assets';
    }

    public function label(): string
    {
        return 'Orphaned Assets';
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

        // Collect asset IDs referenced by canonical, non-deleted elements only.
        // We join craft_elements to exclude provisional drafts and revisions
        // (canonicalId IS NULL = canonical element, not a draft/revision).
        $referencedIds = (new Query())
            ->select(['r.targetId'])
            ->from(['r' => '{{%relations}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[r.sourceId]]')
            ->where([
                'e.dateDeleted' => null,
                'e.canonicalId' => null,
            ])
            ->column();

        // Build element query for assets NOT in the referenced set.
        $assetQuery = Asset::find()->status(null);

        if (!empty($referencedIds)) {
            // array_merge produces ['not', 1, 2, 3] — Craft expects a flat array, not nested
            $assetQuery->id(array_merge(['not'], $referencedIds));
        }

        /** @var Asset[] $orphans */
        $orphans = $assetQuery->all();

        foreach ($orphans as $asset) {
            $issue = new AuditIssue();
            $issue->auditor   = $this->handle();
            $issue->severity  = 'warning';
            $issue->elementId = $asset->id;
            $issue->message   = sprintf(
                '"%s" is uploaded but not used in any entry.',
                $asset->filename
            );
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
