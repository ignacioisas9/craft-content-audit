<?php

namespace kooba\contentaudit\services;

use Craft;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use kooba\contentaudit\auditors\AuditorInterface;
use kooba\contentaudit\auditors\BrokenReferencesAuditor;
use kooba\contentaudit\auditors\LargeAssetsAuditor;
use kooba\contentaudit\auditors\MissingAltTextAuditor;
use kooba\contentaudit\auditors\OrphanedAssetsAuditor;
use kooba\contentaudit\models\AuditIssue;
use yii\base\Component;

/**
 * Orchestrates all registered auditors and persists run results.
 */
class AuditService extends Component
{
    /** @var AuditorInterface[] */
    private array $auditors = [];

    public function init(): void
    {
        parent::init();

        $this->auditors = [
            new OrphanedAssetsAuditor(),
            new MissingAltTextAuditor(),
            new LargeAssetsAuditor(),
            new BrokenReferencesAuditor(),
            // new StaleEntriesAuditor(),   <- Phase 3
            // new MissingMetaAuditor(),    <- Phase 3
        ];
    }

    /**
     * Run all auditors and return the grouped results.
     * Does NOT persist — call saveRun() afterwards.
     *
     * @return array<string, array{label: string, issues: AuditIssue[]}>
     */
    public function runAll(): array
    {
        $results = [];

        foreach ($this->auditors as $auditor) {
            $results[$auditor->handle()] = [
                'label'   => $auditor->label(),
                'columns' => $auditor->columns(),
                'issues'  => $auditor->run(),
            ];
        }

        return $results;
    }

    /**
     * Persist a completed run to the database.
     */
    public function saveRun(array $results, float $duration): void
    {
        $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        Craft::$app->getDb()->createCommand()->insert('{{%content_audit_runs}}', [
            'duration'    => $duration,
            'results'     => Json::encode($this->serializeResults($results)),
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid'         => StringHelper::UUID(),
        ])->execute();
    }

    /**
     * Return the latest stored run, or null if none exists yet.
     *
     * @return array{results: array, duration: float, dateCreated: string}|null
     */
    public function getLatestRun(): ?array
    {
        $row = (new Query())
            ->select(['results', 'duration', 'dateCreated'])
            ->from('{{%content_audit_runs}}')
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if (!$row) {
            return null;
        }

        return [
            'results'     => $this->deserializeResults(Json::decode($row['results'])),
            'duration'    => (float) $row['duration'],
            'dateCreated' => $row['dateCreated'],
        ];
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /** Convert AuditIssue objects → plain arrays for JSON storage. */
    private function serializeResults(array $results): array
    {
        $out = [];
        foreach ($results as $handle => $group) {
            $out[$handle] = [
                'label'   => $group['label'],
                'columns' => $group['columns'],
                'issues'  => array_map(fn(AuditIssue $issue) => [
                    'auditor'   => $issue->auditor,
                    'severity'  => $issue->severity,
                    'elementId' => $issue->elementId,
                    'message'   => $issue->message,
                    'cpEditUrl' => $issue->cpEditUrl,
                    'context'   => $issue->context,
                ], $group['issues']),
            ];
        }
        return $out;
    }

    /** Reconstruct AuditIssue objects from stored plain arrays. */
    private function deserializeResults(array $data): array
    {
        $out = [];
        foreach ($data as $handle => $group) {
            $issues = array_map(function(array $d): AuditIssue {
                $issue            = new AuditIssue();
                $issue->auditor   = $d['auditor'];
                $issue->severity  = $d['severity'];
                $issue->elementId = $d['elementId'] ?? null;
                $issue->message   = $d['message'];
                $issue->cpEditUrl = $d['cpEditUrl'] ?? null;
                $issue->context   = $d['context'] ?? [];
                return $issue;
            }, $group['issues']);

            $out[$handle] = [
                'label'   => $group['label'],
                'columns' => $group['columns'] ?? [],
                'issues'  => $issues,
            ];
        }
        return $out;
    }

    /** @return AuditorInterface[] */
    public function getAuditors(): array
    {
        return $this->auditors;
    }
}
