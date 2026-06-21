<?php

namespace kooba\contentaudit\auditors;

use kooba\contentaudit\models\AuditIssue;

/**
 * Interface for all content auditors.
 *
 * To add a new check, implement this interface and register your
 * auditor in AuditService::init().
 */
interface AuditorInterface
{
    /**
     * A unique machine-readable handle for this auditor.
     * Used as an array key and for filtering.
     * Example: 'orphaned-assets'
     */
    public function handle(): string;

    /**
     * Human-readable label shown in the CP.
     * Example: 'Orphaned Assets'
     */
    public function label(): string;

    /**
     * Run the audit and return any issues found.
     *
     * @return AuditIssue[]
     */
    public function run(): array;

    /**
     * Table column definitions for the CP results view.
     * Each entry: ['key' => 'context_key', 'label' => 'Column Label']
     *
     * @return array<array{key: string, label: string}>
     */
    public function columns(): array;
}
