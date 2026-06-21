<?php

namespace kooba\contentaudit\models;

use craft\base\Model;

/**
 * Represents a single content issue found by an auditor.
 */
class AuditIssue extends Model
{
    /** The auditor handle that produced this issue, e.g. 'orphaned-assets' */
    public string $auditor = '';

    /** 'error' | 'warning' | 'info' */
    public string $severity = 'warning';

    /** The Craft element ID this issue relates to (if any) */
    public ?int $elementId = null;

    /** Human-readable description of the issue */
    public string $message = '';

    /** Direct CP URL to view/edit the offending element */
    public ?string $cpEditUrl = null;

    /** Optional extra context (e.g. filename, volume name) */
    public array $context = [];

    public function rules(): array
    {
        return [
            [['auditor', 'severity', 'message'], 'required'],
            [['severity'], 'in', 'range' => ['error', 'critical', 'warning', 'info']],
        ];
    }
}
