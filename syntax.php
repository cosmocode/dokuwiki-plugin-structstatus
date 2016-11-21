<?php
/**
 * DokuWiki Plugin structstatus (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  A <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_structstatus extends DokuWiki_Syntax_Plugin {
    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 155;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{struct-status>.*?}}', $mode, 'plugin_structstatus');
    }

    /**
     * Handle matches of the structstatus syntax
     *
     * @param string $match The match of the syntax
     * @param int $state The state of the handler
     * @param int $pos The position in the document
     * @param Doku_Handler $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        $match = trim(substr($match, 16, -2));
        list($table, $column) = explode('.', $match, 2);
        return array($table, $column);
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string $mode Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode != 'xhtml') return false;

        $renderer->doc .= $this->tpl($data[0], $data[1]);

        return true;
    }

    public function tpl($table, $field) {
        global $INFO;
        $id = $INFO['id'];
        if( // abort early
            blank($id) ||
            blank($table) ||
            blank($field) ||
            is_null(plugin_load('helper', 'sqlite')) ||
            is_null(plugin_load('helper', 'struct_db'))
        ) return '';

        // check if page has this schema
        $assignments = \dokuwiki\plugin\struct\meta\Assignments::getInstance();
        if(!in_array($table, $assignments->getPageAssignments($id))) return '';

        // check if schema exists
        $schema = new \dokuwiki\plugin\struct\meta\Schema($table);
        if(!$schema->getId()) return '';

        // get the column
        $col = $schema->findColumn($field);
        if(!$col) return '';

        // make sure its the correct type
        /** @var  \dokuwiki\plugin\structstatus\Status $type */
        $type = $col->getType();
        if(!is_a($type, \dokuwiki\plugin\structstatus\Status::class)) {
            msg(hsc("$table.$field is not a Status field"), -1);
            return '';
        }

        // get current value
        $access = \dokuwiki\plugin\struct\meta\AccessTable::bySchema($schema, $id);
        $pids = (array) $access->getDataColumn($col)->getRawValue();

        // add meta data when writable
        $args = array(
            'class' => 'structstatus-full',
        );
        if(auth_quickaclcheck($id) >= AUTH_EDIT && $schema->isEditable()) {
            $args['data-multi'] = (int) $type->isMulti();
            $args['data-st'] = getSecurityToken();
            $args['data-field'] = $col->getFullQualifiedLabel();
            $args['data-page'] = $id;
            $args['class'] .= ' editable';
        }

        // output
        $html = '<div ' . buildAttributes($args) . '>';
        foreach($type->getAllStatuses() as $status) {
            $color = $status['color'];
            if(in_array($status['pid'], $pids)) {
                $class = array();
            } else {
                $class = array('disabled');
            }
            $html .= $type->xhtmlStatus($status['label'], $color, $status['icon'], $status['pid'], $class);
        }
        $html .= '</div>';

        return $html;
    }

}

// vim:ts=4:sw=4:et:
