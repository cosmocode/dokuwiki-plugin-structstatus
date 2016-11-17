<?php

namespace dokuwiki\plugin\structstatus;

use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\types\AbstractBaseType;
use dokuwiki\plugin\struct\types\Lookup;

class Status extends Lookup {

    protected $config = array(
        'schema' => ''
    );

    /**
     * @inheritDoc
     */
    protected function getLookupColumn() {
        if($this->column !== null) return $this->column;
        $this->column = $this->getColumn($this->config['schema'], 'name_$LANG');
        return $this->column;
    }

    /**
     * @inheritDoc
     */
    public function renderValue($value, \Doku_Renderer $R, $mode) {
        list(, $value, $color, $icon) = json_decode($value);

        if($mode == 'xhtml') {
            $class = 'struct_status';
            if($icon) $class .= ' struct_status_icon_' . hsc($icon);
            $R->doc .= '<div class="' . $class . '" style="border-color:' . hsc($color) . '; fill: ' . hsc($color) . ';">';
            $R->doc .= $this->inlineSVG($icon);
            $R->doc .= hsc($value);
            $R->doc .= '</div>';
        } else {
            $R->cdata($value);
        }

        return true;
    }

    /**
     * Returns the svg code of the given icon
     *
     * @param string $icon The icon identifier (no .svg extension)
     * @return string
     */
    protected function inlineSVG($icon) {
        $icon = preg_replace('@[\.\\\\/]+@', '', $icon);
        $file = __DIR__ . '/svg/' . $icon . '.svg';
        if(!file_exists($file)) return '';

        $data = file_get_contents($file);
        $data = preg_replace('/<\?xml .*?\?>/', '', $data);
        $data = preg_replace('/<!DOCTYPE .*?>/', '', $data);

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function renderMultiValue($values, \Doku_Renderer $R, $mode) {
        foreach($values as $value) {
            $this->renderValue($value, $R, $mode);
        }
        return true;
    }

    /**
     * Merge with lookup table
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $alias
     */
    public function select(QueryBuilder $QB, $tablealias, $colname, $alias) {
        $schema = 'data_' . $this->config['schema'];

        $rightalias = $QB->generateTableAlias();

        // main status
        $col_status = $this->getLookupColumn();
        if(!$col_status) {
            AbstractBaseType::select($QB, $tablealias, $colname, $alias);
            return;
        }
        $field_status = $rightalias . '.' . $col_status->getColName();

        // color
        $col_color = $this->getColumn($this->config['schema'], 'color');
        if(!$col_color) {
            $field_color = "'#ffffff'"; // static fallback
        } else {
            $field_color = $rightalias . '.' . $col_color->getColName(true);
        }

        // icon
        $col_icon = $this->getColumn($this->config['schema'], 'icon');
        if(!$col_icon) {
            $field_icon = "''"; // static fallback
        } else {
            $field_icon = $rightalias . '.' . $col_icon->getColName(true);
        }

        // join the lookup
        $QB->addLeftJoin(
            $tablealias, $schema, $rightalias,
            "$tablealias.$colname = $rightalias.pid AND $rightalias.latest = 1"
        );

        // get the values (pid, status, color)
        $QB->addSelectStatement("STRUCT_JSON($tablealias.$colname, $field_status, $field_color, $field_icon)", $alias);
    }
}