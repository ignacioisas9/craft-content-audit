<?php

namespace kooba\contentaudit\auditors;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use kooba\contentaudit\models\AuditIssue;

/**
 * Detects entries whose relational fields (Assets, Entries, etc.)
 * point to elements that have been soft-deleted (moved to trash).
 *
 * Strategy:
 *  - Join craft_relations with craft_elements on the TARGET side.
 *  - Filter where the target element has dateDeleted IS NOT NULL
 *    OR the target element row doesn't exist at all (hard deleted).
 *  - Source must be a canonical, non-deleted element.
 *  - One issue per unique (sourceId, fieldId, targetId) combination.
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

        // Find relations where the target element is trashed, missing, or disabled.
        // LEFT JOIN so we catch targets hard-deleted from the DB too.
        $rows = (new Query())
            ->select([
                'r.sourceId',
                'r.targetId',
                'r.fieldId',
                'e_target.dateDeleted as targetDeletedAt',
                'e_target.enabled as targetEnabled',
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

        // Pre-load field names to avoid N+1 queries on fields service.
        $fieldNames = [];

        foreach ($rows as $row) {
            $sourceId = (int) $row['sourceId'];
            $targetId = (int) $row['targetId'];
            $fieldId  = (int) $row['fieldId'];

            // Load field name (cached by fieldId).
            if (!isset($fieldNames[$fieldId])) {
                $field = Craft::$app->getFields()->getFieldById($fieldId);
                $fieldNames[$fieldId] = $field?->name ?? "Field #{$fieldId}";
            }

            // Load the source element (the entry with the broken link).
            $source = Craft::$app->getElements()->getElementById(
                $sourceId,
                null,
                null,
                ['status' => null]
            );

            if (!$source) {
                continue;
            }

            // Try to find the target's last-known title from elements_sites.
            $targetTitle = (new Query())
                ->select(['title'])
                ->from('{{%elements_sites}}')
                ->where(['elementId' => $targetId])
                ->scalar();

            $sectionLabel = '—';
            if ($source instanceof Entry) {
                $sectionLabel = $source->section?->name ?? '—';
            }

            // Determine severity: deleted/missing = error, disabled = warning.
            $isTrashed  = $row['targetDeletedAt'] !== null;
            $isMissing  = $row['targetEnabled'] === null && $row['targetDeletedAt'] === null;
            $isDisabled = !$isTrashed && !$isMissing && (int) $row['targetEnabled'] === 0;

            $severity   = ($isTrashed || $isMissing) ? 'error' : 'warning';
            $targetSuffix = $isTrashed || $isMissing ? '(deleted)' : '(disabled)';

            $issue = new AuditIssue();
            $issue->auditor   = $this->handle();
            $issue->severity  = $severity;
            $issue->elementId = $sourceId;
            $issue->message   = sprintf(
                '"%s" references a %s element via field "%s".',
                $source->title ?? "Element #{$sourceId}",
                $isDisabled ? 'disabled' : 'deleted',
                $fieldNames[$fieldId]
            );
            $issue->cpEditUrl = $source->cpEditUrl;
            $issue->context   = [
                'title'   => $source->title ?? "Element #{$sourceId}",
                'section' => $sectionLabel,
                'field'   => $fieldNames[$fieldId],
                'target'  => $targetTitle ? "\"{$targetTitle}\" {$targetSuffix}" : "Element #{$targetId} {$targetSuffix}",
            ];

            $issues[] = $issue;
        }

        return $issues;
    }
}
