<?php

use dokuwiki\plugin\sqlite\SQLiteDB;

/**
 * DokuWiki Plugin structstatus (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */
class action_plugin_structstatus extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('PLUGIN_STRUCT_TYPECLASS_INIT', 'BEFORE', $this, 'handleInit');
       $controller->register_hook('PLUGIN_STRUCT_TYPECLASS_INIT', 'AFTER', $this, 'handleMigrations');

    }

    /**
     * @param Doku_Event $event
     * @return void
     */

    public function handleInit(Doku_Event $event) {
        $event->data['Status'] = 'dokuwiki\\plugin\\structstatus\\Status';
    }

    /**
     * Call our custom migrations when defined
     *
     * @param Doku_Event $event
     * @return bool
     */
    public function handleMigrations(Doku_Event $event)
    {
        /** @var \helper_plugin_struct_db $helper */
        $helper = plugin_load('helper', 'struct_db');
        $sqlite = $helper->getDB();

        // check whether we are already up-to-date
        list($dbVersionStruct, $dbVersionStructStatus) = $this->getDbVersions($sqlite);
        if (isset($dbVersionStructStatus) && $dbVersionStructStatus === $dbVersionStruct) {
            return true;
        }

        // check whether we have any pending migrations for the current version of struct db
        $pending = array_filter(array_map(function ($version) use ($dbVersionStruct) {
            return $version >= $dbVersionStruct &&
                is_callable([$this, "migration$version"]) ? $version : null;
        }, $this->diffVersions($dbVersionStruct, $dbVersionStructStatus)));
        if (empty($pending)) {
            return true;
        }

        // execute the migrations
        $sql = "SELECT MAX(id) AS id, tbl FROM schemas GROUP BY tbl";
        $schemas= $sqlite->queryAll($sql);

        $sqlite->getPdo()->beginTransaction();
        try {

            foreach ($pending as $version) {
                $call = 'migration' . $version;
                foreach ($schemas as $schema) {
                    $this->$call($sqlite, $schema);
                }
            }

            // update migration status in struct database
            $sql = 'REPLACE INTO opts(opt,val) VALUES ("dbversion_structstatus", ' . $version . ')';
            $sqlite->query($sql);

            $sqlite->getPdo()->commit();
        } catch (Exception $e) {
            $sqlite->getPdo()->rollBack();
            return false;
        }

        return true;
    }

    /**
     * Detect which migrations should be executed. Start conservatively with version 1.
     *
     * @param int $dbVersionStruct Current version of struct DB as found in 'opts' table
     * @param int|null $dbVersionStructStatus Current version in 'opts', may not exist yet
     * @return int[]
     */
    protected function diffVersions($dbVersionStruct, $dbVersionStructStatus)
    {
        $pluginDbVersion = $dbVersionStructStatus ?: 1;
        return range($pluginDbVersion, $dbVersionStruct);
    }

    /**
     * Converts integer ids used in struct before dbversion 17
     * to composite ids ["",int]
     *
     * @param SQLiteDB $sqlite
     * @param array $schema
     * @return bool
     */
    protected function migration17($sqlite, $schema)
    {
        $name = $schema['tbl'];
        $sid = $schema['id'];

        $s = $this->getLookupColsSql($sid);
        $cols = $sqlite->queryAll($s);

        if ($cols) {
            foreach ($cols as $col) {
                $colno = $col['COL'];
                $s = "UPDATE data_$name SET col$colno = '[" . '""' . ",'||col$colno||']' WHERE col$colno != '' AND CAST(col$colno AS DECIMAL) = col$colno";
                $ok = true && $sqlite->query($s);
                if (!$ok) return false;
                // multi_
                $s = "UPDATE multi_$name SET value = '[" . '""' . ",'||value||']' WHERE colref=$colno AND CAST(value AS DECIMAL) = value";
                $ok = $ok && $sqlite->query($s);
                if (!$ok) return false;
            }
        }

        return true;
    }

    /**
     * @param SQLiteDB $sqlite
     * @return array
     */
    protected function getDbVersions($sqlite)
    {
        $dbVersionStruct = null;
        $dbVersionStructStatus = null;

        $sql = 'SELECT opt, val FROM opts WHERE opt=? OR opt=?';
        $vals = $sqlite->queryAll($sql, ['dbversion', 'dbversion_structstatus']);

        foreach ($vals as $val) {
            if ($val['opt'] === 'dbversion') {
                $dbVersionStruct = $val['val'];
            }
            if ($val['opt'] === 'dbversion_structstatus') {
                $dbVersionStructStatus = $val['val'];
            }
        }
         return [$dbVersionStruct, $dbVersionStructStatus];
    }

    /**
     * Returns a select statement to fetch our columns in the current schema
     *
     * @param int $sid Id of the schema
     * @return string SQL statement
     */
    protected function getLookupColsSql($sid)
    {
        return "SELECT C.colref AS COL, T.class AS TYPE
                FROM schema_cols AS C
                LEFT OUTER JOIN types AS T
                    ON C.tid = T.id
                WHERE C.sid = $sid
                AND (TYPE = 'Status')
            ";
    }
}

// vim:ts=4:sw=4:et:
