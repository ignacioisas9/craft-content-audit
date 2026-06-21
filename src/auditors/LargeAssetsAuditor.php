<?php

namespace iistudio\contentaudit\auditors;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use iistudio\contentaudit\models\AuditIssue;

/**
 * Flags assets whose file size exceeds a configurable threshold.
 *
 * Default threshold: 2 MB. Oversized uploads slow page loads and
 * bloat storage costs.
 *
 * To customise the threshold, add a config file at
 * config/content-audit.php in your Craft project:
 *
 *   return [
 *       'largeAssetThreshold' => 5 * 1024 * 1024, // 5 MB
 *   ];
 */
class LargeAssetsAuditor implements AuditorInterface
{
    /** Default threshold in bytes (2 MB). Override via config/content-audit.php. */
    private const DEFAULT_THRESHOLD_BYTES = 2 * 1024 * 1024;

    /** Assets at or above this size are flagged as 'critical' instead of 'warning'. */
    private const CRITICAL_THRESHOLD_BYTES = 5 * 1024 * 1024;

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
        $issues    = [];
        $threshold = $this->getThresholdBytes();

        // Find asset IDs over the threshold, excluding drafts and deleted elements.
        $ids = (new Query())
            ->select(['a.id'])
            ->from(['a' => '{{%assets}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[a.id]]')
            ->where([
                'e.dateDeleted' => null,
                'e.canonicalId' => null,
            ])
            ->andWhere(['>', 'a.size', $threshold])
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
            $issue->severity  = $asset->size >= self::CRITICAL_THRESHOLD_BYTES ? 'critical' : 'warning';
            $issue->elementId = $asset->id;
            $issue->message   = sprintf(
                '"%s" is %s — over the %s threshold.',
                $asset->filename,
                Craft::$app->getFormatter()->asShortSize($asset->size),
                Craft::$app->getFormatter()->asShortSize($threshold)
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

    /**
     * Returns the configured threshold in bytes, falling back to the default.
     */
    private function getThresholdBytes(): int
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('content-audit');
        return (int) ($config['largeAssetThreshold'] ?? self::DEFAULT_THRESHOLD_BYTES);
    }
}
