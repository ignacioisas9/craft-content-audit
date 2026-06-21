<?php

namespace iistudio\contentaudit\controllers;

use Craft;
use craft\web\Controller;
use iistudio\contentaudit\ContentAudit;
use yii\web\Response;

/**
 * Handles CP requests for the Content Audit plugin.
 */
class CpController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * Show the audit dashboard, loading the latest persisted run if one exists.
     */
    public function actionIndex(): Response
    {
        $this->requireLogin();
        $this->requirePermission('accessPlugin-content-audit');

        $audit     = ContentAudit::getInstance()->audit;
        $latestRun = $audit->getLatestRun();

        return $this->renderTemplate('content-audit/_cp/index', [
            'results'       => $latestRun['results'] ?? null,
            'duration'      => $latestRun['duration'] ?? null,
            'lastRunDate'   => $latestRun['dateCreated'] ?? null,
            'auditError'    => null,
            'auditorLabels' => $this->getAuditorLabels(),
        ]);
    }

    /**
     * Run all auditors, persist the result, then render.
     */
    public function actionRun(): Response
    {
        $this->requireLogin();
        $this->requirePermission('accessPlugin-content-audit');
        $this->requirePostRequest();

        $audit = ContentAudit::getInstance()->audit;
        $start = microtime(true);

        try {
            $results  = $audit->runAll();
            $duration = round(microtime(true) - $start, 2);
            $audit->saveRun($results, $duration);
        } catch (\Throwable $e) {
            Craft::error('Content Audit run failed: ' . $e->getMessage(), __METHOD__);

            $latestRun = $audit->getLatestRun();

            return $this->renderTemplate('content-audit/_cp/index', [
                'results'       => $latestRun['results'] ?? null,
                'duration'      => $latestRun['duration'] ?? null,
                'lastRunDate'   => $latestRun['dateCreated'] ?? null,
                'auditError'    => Craft::t('content-audit', 'The audit failed: {error}', [
                    'error' => $e->getMessage(),
                ]),
                'auditorLabels' => $this->getAuditorLabels(),
            ]);
        }

        $lastRunDate = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        return $this->renderTemplate('content-audit/_cp/index', [
            'results'       => $results,
            'duration'      => $duration,
            'lastRunDate'   => $lastRunDate,
            'auditError'    => null,
            'auditorLabels' => $this->getAuditorLabels(),
        ]);
    }

    /**
     * Returns a plain array of auditor label strings for the template.
     */
    private function getAuditorLabels(): array
    {
        $labels = [];
        foreach (ContentAudit::getInstance()->audit->getAuditors() as $auditor) {
            $labels[] = $auditor->label();
        }
        return $labels;
    }
}
