<?php

namespace iistudio\contentaudit\auditors;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use iistudio\contentaudit\models\AuditIssue;

/**
 * Detects entries whose relational fields point to elements that are
 * trashed, hard-deleted, or disabled.
 *
 * - Trashed / hard-deleted targets → severity 'error'
 * - Disabled targets               → severity 'warning'
 */
class BrokenReferencesAuditor implements AuditorInterface
{
    public function handle(): string
    {
        return 'broken-references';
    }

    public function label(): string
    {
        return 'Broken References';
    }

    public function columns(): array
    {
        return [
            ['key' => 'title',   'label' => 'Entry'],
            ['key' => 'section', 'label' => 'Section'],
            ['key' => 'field',   'label' => 'Broken field'],
            ['key' => 'target',  'label' => 'Missing element'],
        ];
    }

    public function run(): array
    {
        $issues = [];

        $rows = (new Query())
            ->select([
                'r.sourceId',
                'r.targetId',
                'r.fieldId',
                'e_target.dateDeleted as targetDeletedAt',
                'e_target.enabled    as targetEnabled',
            ])
            ->distinct()
            ->from(['r' => '{{%relations}}'])
            ->innerJoin(['e_source' => '{{%elements}}'], '[[e_source.id]] = [[r.sourceId]]')
            ->leftJoin(['e_target' => '{{%elements}}'], '[[e_target.id]] = [[r.targetId]]')
            ->where([
                'e_source.dateDeleted' => null,
                'e_source.canonicalId' => null,
            ])
            ->andWhere(['or',
                ['not', ['e_target.dateDeleted' => null]],  // target is trashed
                ['e_target.id' => null],                    // target was hard-deleted
                ['e_target.enabled' => 0],                  // target is disabled
            ])
            ->all();

        if (empty($rows)) {
            return [];
        }

        // Bulk-load source entries to avoid N+1 queries.
        $sourceIds = array_unique(array_map('intval', array_column($rows, 'sourceId')));
        $targetIds = array_unique(array_map('intval', array_column($rows, 'targetId')));

        $sourceElements = Entry::find()
            ->id($sourceIds)
            ->status(null)
            ->indexBy('id')
            ->all();

        // Bulk-load last-known titles for broken targets in a single query.
        $targetTitleMap = [];
        if (!empty($targetIds)) {
            $titleRows = (new Query())
                ->select(['elementId', 'title'])
                ->from('{{%elements_sites}}')
                ->where(['elementId' => $targetIds])
                ->all();
            foreach ($titleRows as $r) {
                $targetTitleMap[(int) $r['elementId']] = $r['title'];
            }
        }

        $fieldNames = [];

        foreach ($rows as $row) {
            $sourceId = (int) $row['sourceId'];
            $targetId = (int) $row['targetId'];
            $fieldId  = (int) $row['fieldId'];

            if (!isset($fieldNames[$fieldId])) {
                $field = Craft::$app->getFields()->getFieldById($fieldId);
                $fieldNames[$fieldId] = $field?->name ?? "Field #{$fieldId}";
            }

            $source = $sourceElements[$sourceId] ?? null;
            if (!$source) {
                continue;
            }

            $targetTitle  = $targetTitleMap[$targetId] ?? null;
            $sectionLabel = $source instanceof Entry ? ($source->section?->name ?? '—') : '—';

            $isTrashed  = $row['targetDeletedAt'] !== null;
            $isMissing  = $row['targetEnabled'] === null && $row['targetDeletedAt'] === null;
            $isDisabled = !$isTrashed && !$isMissing && (int) $row['targetEnabled'] === 0;

            $severity     = ($isTrashed || $isMissing) ? 'error' : 'warning';
            $targetSuffix = $isDisabled ? 'disabled' : ($isTrashed ? 'trashed' : 'hard-deleted');

            $issue = new AuditIssue();
            $issue->auditor   = $this->handle();
            $issue->severity  = $severity;
            $issue->elementId = $sourceId;
            $issue->message   = sprintf(
                '"%s" references a %s element via field "%s".',
                $source->title ?? "Element #{$sourceId}",
                $targetSuffix,
                $fieldNames[$fieldId]
            );
            $issue->cpEditUrl = $source->cpEditUrl;
            $issue->context   = [
                'title'   => $source->title ?? "Element #{$sourceId}",
                'section' => $sectionLabel,
                'field'   => $fieldNames[$fieldId],
                'target'  => $targetTitle
                    ? "\"{$targetTitle}\" ({$targetSuffix})"
                    : "Element #{$targetId} ({$targetSuffix})",
            ];

            $issues[] = $issue;
        }

        return $issues;
    }
}
