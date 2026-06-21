<?php

namespace kooba\contentaudit;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use kooba\contentaudit\services\AuditService;
use yii\base\Event;

/**
 * Content Audit plugin for Craft CMS 5
 *
 * @property AuditService $audit
 */
class ContentAudit extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'audit' => AuditService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Register alias so @kooba/contentaudit resolves to the plugin root (one level up from src/)
        Craft::setAlias('@kooba/contentaudit', dirname($this->basePath));

        // Register CP URL rules
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                function (RegisterUrlRulesEvent $event) {
                    $event->rules['content-audit'] = 'content-audit/cp/index';
                    $event->rules['content-audit/run'] = 'content-audit/cp/run';
                }
            );
        }
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = Craft::t('content-audit', 'Content Audit');
        $item['url'] = 'content-audit';
        $item['icon'] = $this->basePath . '/../icon.svg';
        return $item;
    }
}
