<?php

namespace kooba\contentaudit\controllers;

use craft\web\Controller;
use kooba\contentaudit\ContentAudit;
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

        $latestRun = ContentAudit::getInstance()->audit->getLatestRun();

        return $this->renderTemplate('content-audit/_cp/index', [
            'results'     => $latestRun['results'] ?? null,
            'duration'    => $latestRun['duration'] ?? null,
            'lastRunDate' => $latestRun['dateCreated'] ?? null,
        ]);
    }

    /**
     * Run all auditors, persist the result, then render.
     */
    public function actionRun(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $audit    = ContentAudit::getInstance()->audit;
        $start    = microtime(true);
        $results  = $audit->runAll();
        $duration = round(microtime(true) - $start, 2);

        $audit->saveRun($results, $duration);

        $lastRunDate = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        return $this->renderTemplate('content-audit/_cp/index', [
            'results'     => $results,
            'duration'    => $duration,
            'lastRunDate' => $lastRunDate,
        ]);
    }
}
