<?php

namespace iistudio\contentaudit\events;

use iistudio\contentaudit\auditors\AuditorInterface;
use yii\base\Event;

/**
 * Event fired during AuditService::init() to allow other plugins
 * to register additional auditors.
 *
 * Usage:
 *
 *   use iistudio\contentaudit\services\AuditService;
 *   use iistudio\contentaudit\events\RegisterAuditorsEvent;
 *   use yii\base\Event;
 *
 *   Event::on(
 *       AuditService::class,
 *       AuditService::EVENT_REGISTER_AUDITORS,
 *       function (RegisterAuditorsEvent $event) {
 *           $event->auditors[] = new MyCustomAuditor();
 *       }
 *   );
 */
class RegisterAuditorsEvent extends Event
{
    /** @var AuditorInterface[] */
    public array $auditors = [];
}
