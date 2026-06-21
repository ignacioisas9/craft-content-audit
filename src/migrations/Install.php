<?php

namespace kooba\contentaudit\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%content_audit_runs}}')) {
            $this->createTable('{{%content_audit_runs}}', [
                'id'          => $this->primaryKey(),
                'duration'    => $this->float()->notNull()->defaultValue(0),
                'results'     => $this->mediumText()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid'         => $this->uid(),
            ]);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%content_audit_runs}}');

        return true;
    }
}
