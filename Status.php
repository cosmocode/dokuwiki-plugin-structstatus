<?php

namespace dokuwiki\plugin\structstatus;

use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\Search;
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
            $R->doc .= $this->xhtmlStatus($value, $color, $icon);
        } else {
            $R->cdata($value);
        }

        return true;
    }

    /**
     * Creates a single status entry
     *
     * @param string $label
     * @param string $color
     * @param string $icon
     * @param string $pid the identifier in the linked status lookup table
     * @param array $classes
     * @param bool  $button
     * @return string
     */
    public function xhtmlStatus($label, $color, $icon='', $pid='', $classes=array(), $button=false) {
        $html = '';
        $classes[] = 'struct_status';
        if($icon) $classes[] = 'struct_status_icon_'.$icon;
        $class = hsc(join(' ', $classes));

        $tag = $button ? 'button' : 'div';

        $html .= "<$tag class=\"" . $class . '" style="border-color:' . hsc($color) . '; fill: ' . hsc($color) . ';" data-pid="'.hsc($pid).'">';
        $html .= $this->inlineSVG($icon);
        $html .= hsc($label);
        $html .= "</$tag>";

        return $html;
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
            "$tablealias.$colname = STRUCT_JSON($rightalias.pid, CAST($rightalias.rid AS DECIMAL)) AND $rightalias.latest = 1"
        );

        // get the values (pid, status, color)
        $QB->addSelectStatement("STRUCT_JSON($tablealias.$colname, $field_status, $field_color, $field_icon)", $alias);
    }

    /**
     * Returns a list of available statuses for this type
     *
     * This is similar to getOptions but returns some more info about each status
     *
     * @return array
     */
    public function getAllStatuses() {
        $col = $this->getLookupColumn();
        $colname = $col->getLabel();

        $search = new Search();
        $search->addSchema($col->getTable());
        $search->addColumn($colname);
        $search->addColumn('color');
        $search->addColumn('icon');
        $search->addSort($colname);
        $values = $search->execute();
        $pids = $search->getPids();

        $statuses = array();
        foreach($values as $status) {
            $pid = array_shift($pids);
            $label = $status[0]->getValue();
            $color = $status[1]->getValue();
            $icon = $status[2]->getValue();

            $statuses[] = compact('pid', 'label', 'color', 'icon');
        }

        return $statuses;
    }
}
