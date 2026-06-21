<?php

namespace kooba\contentaudit\auditors;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use kooba\contentaudit\models\AuditIssue;

/**
 * Flags assets whose file size exceeds a configurable threshold.
 *
 * Default threshold: 2 MB. Oversized uploads are a common performance
 * issue — large images slow page loads and bloat storage costs.
 *
 * To change the threshold, override THRESHOLD_BYTES in a subclass or
 * update the constant here before plugin submission.
 */
class LargeAssetsAuditor implements AuditorInterface
{
    /** Flag assets larger than this (bytes). Default: 2 MB. */
    private const THRESHOLD_BYTES = 2 * 1024 * 1024;

    public function handle(): string
    {
        return 'large-assets';
    }

    public function label(): string
    {
        return 'Large Assets';
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

        // Find asset IDs over the threshold, excluding drafts and deleted elements.
        $ids = (new Query())
            ->select(['a.id'])
            ->from(['a' => '{{%assets}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[a.id]]')
            ->where([
                'e.dateDeleted' => null,
                'e.canonicalId' => null,
            ])
            ->andWhere(['>', 'a.size', self::THRESHOLD_BYTES])
            ->column();

        if (empty($ids)) {
            return [];
        }

        /** @var Asset[] $assets */
        $assets = Asset::find()
            ->id($ids)
            ->status(null)
            ->orderBy(['size' => SORT_DESC])
            ->all();

        foreach ($assets as $asset) {
            $issue = new AuditIssue();
            $issue->auditor   = $this->handle();
            $issue->severity  = $asset->size >= 5 * 1024 * 1024 ? 'critical' : 'warning';
            $issue->elementId = $asset->id;
            $issue->message   = sprintf(
                '"%s" is %s — over the %s threshold.',
                $asset->filename,
                Craft::$app->getFormatter()->asShortSize($asset->size),
                Craft::$app->getFormatter()->asShortSize(self::THRESHOLD_BYTES)
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
