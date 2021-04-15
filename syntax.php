<?php

/*
 * This plugin extends DokuWiki's list markup syntax to allow definition lists
 * and list items with multiple paragraphs. The complete syntax is as follows:
 *
 *
 *   - ordered list item            [<ol><li>]  <!-- as standard syntax -->
 *   * unordered list item          [<ul><li>]  <!-- as standard syntax -->
 *   ? definition list term         [<dl><dt>]
 *   : definition list definition   [<dl><dd>]
 *
 *   -- ordered list item w/ multiple paragraphs
 *   ** unordered list item w/ multiple paragraphs
 *   :: definition list definition w/multiple paragraphs
 *   .. new paragraph in --, **, or ::
 *
 *
 * Lists can be nested within lists, just as in the standard DokuWiki syntax.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Ben Slusky <sluskyb@paranoiacs.org>
 *
 */

class syntax_plugin_yalist extends DokuWiki_Syntax_Plugin {
    private static $odt_table_stack = array();
    private static $odt_table_stack_index = 0;
    private $stack = array();

    public function getType() {
        return 'container';
    }

    public function getSort() {
        // just before listblock (10)
        return 9;
    }

    public function getPType() {
        return 'block';
    }

    public function getAllowedTypes() {
        return array('substition', 'protected', 'disabled', 'formatting');
    }

    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('\n {2,}(?:--?|\*\*?|\?|::?)', $mode, 'plugin_yalist');
        $this->Lexer->addEntryPattern('\n\t{1,}(?:--?|\*\*?|\?|::?)', $mode, 'plugin_yalist');
        $this->Lexer->addPattern('\n {2,}(?:--?|\*\*?|\?|::?|\.\.)', 'plugin_yalist');
        $this->Lexer->addPattern('\n\t{1,}(?:--?|\*\*?|\?|::?|\.\.)', 'plugin_yalist');
    }

    public function postConnect() {
        $this->Lexer->addExitPattern('\n', 'plugin_yalist');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler) {
        $output = array();
        $level  = 0;
        switch($state) {
            case DOKU_LEXER_ENTER:
                $frame = $this->interpretMatch($match);
                $level = $frame['level'] = 1;
                array_push(
                    $output,
                    "${frame['list']}_open",
                    "${frame['item']}_open",
                    "${frame['item']}_content_open"
                );
                if($frame['paras']) {
                    array_push($output, 'p_open');
                }
                array_push($this->stack, $frame);
                break;
            case DOKU_LEXER_EXIT:
                $close_content = true;
                while($frame = array_pop($this->stack)) {
                    // for the first frame we pop off the stack, we'll need to
                    // close the content tag; for the rest it will have been
                    // closed already
                    if($close_content) {
                        if($frame['paras']) {
                            array_push($output, 'p_close');
                        }
                        array_push($output, "${frame['item']}_content_close");
                        $close_content = false;
                    }
                    array_push(
                        $output,
                        "${frame['item']}_close",
                        "${frame['list']}_close"
                    );
                }
                break;
            case DOKU_LEXER_MATCHED:
                $last_frame = end($this->stack);
                if(substr($match, -2) == '..') {
                    // new paragraphs cannot be deeper than the current depth,
                    // but they may be shallower
                    $para_depth    = count(explode('  ', str_replace("\t", '  ', $match)));
                    $close_content = true;
                    while($para_depth < $last_frame['depth'] && count($this->stack) > 1) {
                        if($close_content) {
                            if($last_frame['paras']) {
                                array_push($output, 'p_close');
                            }
                            array_push($output, "${last_frame['item']}_content_close");
                            $close_content = false;
                        }
                        array_push(
                            $output,
                            "${last_frame['item']}_close",
                            "${last_frame['list']}_close"
                        );
                        array_pop($this->stack);
                        $last_frame = end($this->stack);
                    }
                    if($last_frame['paras']) {
                        if($close_content) {
                            // depth did not change
                            array_push($output, 'p_close', 'p_open');
                        } else {
                            array_push(
                                $output,
                                "${last_frame['item']}_content_open",
                                'p_open'
                            );
                        }
                    } else {
                        // let's just pretend we didn't match...
                        $state  = DOKU_LEXER_UNMATCHED;
                        $output = $match;
                    }
                    break;
                }
                $curr_frame = $this->interpretMatch($match);
                if($curr_frame['depth'] > $last_frame['depth']) {
                    // going one level deeper
                    $level = $last_frame['level'] + 1;
                    if($last_frame['paras']) {
                        array_push($output, 'p_close');
                    }
                    array_push(
                        $output,
                        "${last_frame['item']}_content_close",
                        "${curr_frame['list']}_open"
                    );
                } else {
                    // same depth, or getting shallower
                    $close_content = true;
                    // keep popping frames off the stack until we find a frame
                    // that's at least as deep as this one, or until only the
                    // bottom frame (i.e. the initial list markup) remains
                    while($curr_frame['depth'] < $last_frame['depth'] &&
                        count($this->stack) > 1) {
                        // again, we need to close the content tag only for
                        // the first frame popped off the stack
                        if($close_content) {
                            if($last_frame['paras']) {
                                array_push($output, 'p_close');
                            }
                            array_push($output, "${last_frame['item']}_content_close");
                            $close_content = false;
                        }
                        array_push(
                            $output,
                            "${last_frame['item']}_close",
                            "${last_frame['list']}_close"
                        );
                        array_pop($this->stack);
                        $last_frame = end($this->stack);
                    }
                    // pull the last frame off the stack;
                    // it will be replaced by the current frame
                    array_pop($this->stack);
                    $level = $last_frame['level'];
                    if($close_content) {
                        if($last_frame['paras']) {
                            array_push($output, 'p_close');
                        }
                        array_push($output, "${last_frame['item']}_content_close");
                        $close_content = false;
                    }
                    array_push($output, "${last_frame['item']}_close");
                    if($curr_frame['list'] != $last_frame['list']) {
                        // change list types
                        array_push(
                            $output,
                            "${last_frame['list']}_close",
                            "${curr_frame['list']}_open"
                        );
                    }
                }
                // and finally, open tags for the new list item
                array_push(
                    $output,
                    "${curr_frame['item']}_open",
                    "${curr_frame['item']}_content_open"
                );
                if($curr_frame['paras']) {
                    array_push($output, 'p_open');
                }
                $curr_frame['level'] = $level;
                array_push($this->stack, $curr_frame);
                break;
            case DOKU_LEXER_UNMATCHED:
                $output = $match;
                break;
        }
        return array('state' => $state, 'output' => $output, 'level' => $level);
    }

    private function interpretMatch($match) {
        $tag_table = array(
            '*' => 'u_li',
            '-' => 'o_li',
            '?' => 'dt',
            ':' => 'dd',
        );
        $tag       = $tag_table[substr($match, -1)];
        return array(
            'depth' => count(explode('  ', str_replace("\t", '  ', $match))),
            'list'  => substr($tag, 0, 1) . 'l',
            'item'  => substr($tag, -2),
            'paras' => (substr($match, -1) == substr($match, -2, 1)),
        );
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode != 'xhtml' && $mode != 'latex' && $mode != 'odt') {
            return false;
        }
        if($data['state'] == DOKU_LEXER_UNMATCHED) {
            if($mode != 'odt') {
                $renderer->doc .= $renderer->_xmlEntities($data['output']);
            } else {
                $renderer->cdata($data['output']);
            }
            return true;
        }
        foreach($data['output'] as $i) {
            switch($mode) {
                case 'xhtml':
                    $this->renderXhtmlItem($renderer, $i, $data);
                    break;
                case 'latex':
                    $this->renderLatexItem($renderer, $i, $data);
                    break;
                case 'odt':
                    $this->renderOdtItem($renderer, $i, $data);
                    break;
            }
        }
        if($data['state'] == DOKU_LEXER_EXIT) {
            if($mode != 'odt') {
                $renderer->doc .= "\n";
            } else {
                $renderer->linebreak();
            }
        }
        return true;
    }

    private function renderXhtmlItem(Doku_Renderer $renderer, $item, $data) {
        $markup = '';
        switch($item) {
            case 'ol_open':
                $markup = "<ol>\n";
                break;
            case 'ol_close':
                $markup = "</ol>\n";
                break;
            case 'ul_open':
                $markup = "<ul>\n";
                break;
            case 'ul_close':
                $markup = "</ul>\n";
                break;
            case 'dl_open':
                $markup = "<dl>\n";
                break;
            case 'dl_close':
                $markup = "</dl>\n";
                break;
            case 'li_open':
                $markup = "<li class=\"level${data['level']}\">";
                break;
            case 'li_content_open':
                $markup = "<div class=\"li\">\n";
                break;
            case 'li_content_close':
            case 'dd_content_close':
                $markup = "\n</div>";
                break;
            case 'li_close':
                $markup = "</li>\n";
                break;
            case 'dt_open':
                $markup = "<dt class=\"level${data['level']}\">";
                break;
            case 'dt_content_open':
                $markup = "<span class=\"dt\">";
                break;
            case 'dt_content_close':
                $markup = "</span>";
                break;
            case 'dt_close':
                $markup = "</dt>\n";
                break;
            case 'dd_open':
                $markup = "<dd class=\"level${data['level']}\">";
                break;
            case 'dd_content_open':
                $markup = "<div class=\"dd\">\n";
                break;
            case 'dd_close':
                $markup = "</dd>\n";
                break;
            case 'p_open':
                $markup = "<p>\n";
                break;
            case 'p_close':
                $markup = "\n</p>";
                break;
        }
        $renderer->doc .= $markup;
    }

    private function renderLatexItem(Doku_Renderer $renderer, $item) {
        $markup = '';
        switch($item) {
            case 'ol_open':
                $markup = "\\begin{enumerate}\n";
                break;
            case 'ol_close':
                $markup = "\\end{enumerate}\n";
                break;
            case 'ul_open':
                $markup = "\\begin{itemize}\n";
                break;
            case 'ul_close':
                $markup = "\\end{itemize}\n";
                break;
            case 'dl_open':
                $markup = "\\begin{description}\n";
                break;
            case 'dl_close':
                $markup = "\\end{description}\n";
                break;
            case 'li_open':
                $markup = "\item ";
                break;
            case 'li_content_open':
                break;
            case 'li_content_close':
                break;
            case 'li_close':
                $markup = "\n";
                break;
            case 'dt_open':
                $markup = "\item[";
                break;
            case 'dt_content_open':
                break;
            case 'dt_content_close':
                break;
            case 'dt_close':
                $markup = "] ";
                break;
            case 'dd_open':
                break;
            case 'dd_content_open':
                break;
            case 'dd_content_close':
                break;
            case 'dd_close':
                $markup = "\n";
                break;
            case 'p_open':
                $markup = "\n";
                break;
            case 'p_close':
                $markup = "\n";
                break;
        }
        $renderer->doc .= $markup;
    }

    /**
     * Render yalist items for ODT format
     *
     * @param Doku_Renderer $renderer The current renderer object
     * @param string        $item     The item to render
     *
     * @author LarsDW223
     */
    private function renderOdtItem(Doku_Renderer $renderer, $item) {
        switch($item) {
            case 'ol_open':
                $renderer->listo_open();
                break;
            case 'ul_open':
                $renderer->listu_open();
                break;
            case 'dl_open':
                if($this->getConf('def_list_odt_export') != 'table') {
                    $renderer->listu_open();
                } else {
                    $renderer->table_open(2);
                }
                self::$odt_table_stack [self::$odt_table_stack_index]             = array();
                self::$odt_table_stack [self::$odt_table_stack_index]['itemOpen'] = false;
                self::$odt_table_stack [self::$odt_table_stack_index]['dtState']  = 0;
                self::$odt_table_stack [self::$odt_table_stack_index]['ddState']  = 0;
                self::$odt_table_stack_index++;
                break;
            case 'ol_close':
            case 'ul_close':
                $renderer->list_close();
                break;
            case 'dl_close':
                $config = $this->getConf('def_list_odt_export');
                if($config != 'table') {
                    if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['ddState'] != 2) {
                        if($config == 'listheader' && method_exists($renderer, 'listheader_close')) {
                            $renderer->listheader_close();
                        } else {
                            $renderer->listitem_close();
                        }
                    }
                    self::$odt_table_stack [self::$odt_table_stack_index - 1]['ddState'] = 0;
                    $renderer->list_close();
                } else {
                    if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['ddState'] == 0) {
                        $properties            = array();
                        $properties ['border'] = 'none';
                        $renderer->_odtTableCellOpenUseProperties($properties);
                        $renderer->tablecell_close();
                    }
                    self::$odt_table_stack [self::$odt_table_stack_index - 1]['ddState'] = 0;
                    if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === true) {
                        $renderer->tablerow_close(1);
                        self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = false;
                    }
                    $renderer->table_close();
                }
                if(self::$odt_table_stack_index > 0) {
                    self::$odt_table_stack_index--;
                    unset(self::$odt_table_stack [self::$odt_table_stack_index]);
                }
                break;

            case 'li_open':
                $renderer->listitem_open(1);
                break;
            case 'li_content_open':
                $renderer->listcontent_open();
                break;
            case 'li_content_close':
                $renderer->listcontent_close();
                break;
            case 'li_close':
                $renderer->listitem_close();
                break;

            case 'dt_open': // unconditional: DT tags can't contain paragraphs. That would not be legal XHTML.
                switch($this->getConf('def_list_odt_export')) {
                    case 'listheader':
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === true) {
                            if(method_exists($renderer, 'listheader_close')) {
                                $renderer->listheader_close();
                            } else {
                                $renderer->listitem_close();
                            }
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = false;
                        }
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === false) {
                            if(method_exists($renderer, 'listheader_open')) {
                                $renderer->listheader_open(1);
                            } else {
                                $renderer->listitem_open(1);
                            }
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = true;
                        }
                        break;
                    case 'table':
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['ddState'] == 0) {
                            $properties            = array();
                            $properties ['border'] = 'none';
                            $renderer->_odtTableCellOpenUseProperties($properties);
                            $renderer->tablecell_close();
                        }

                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === true) {
                            $renderer->tablerow_close();
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = false;
                        }
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === false) {
                            $renderer->tablerow_open(1);
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = true;
                        }
                        $properties            = array();
                        $properties ['border'] = 'none';
                        $renderer->_odtTableCellOpenUseProperties($properties);
                        break;
                    default:
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === true) {
                            $renderer->listitem_close();
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = false;
                        }
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === false) {
                            $renderer->listitem_open(1);
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = true;
                        }
                        break;
                }
                self::$odt_table_stack [self::$odt_table_stack_index - 1]['dtState'] = 1;
                self::$odt_table_stack [self::$odt_table_stack_index - 1]['ddState'] = 0;
                break;
            case 'dd_open':
                switch($this->getConf('def_list_odt_export')) {
                    case 'listheader':
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === false) {
                            if(method_exists($renderer, 'listheader_open')) {
                                $renderer->listheader_open(1);
                            } else {
                                $renderer->listitem_open(1);
                            }
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = true;
                        }
                        break;
                    case 'table':
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === false) {
                            $renderer->tablerow_open(1);
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = true;
                        }
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['dtState'] == 1) {
                            $renderer->tablecell_close();
                        }
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['dtState'] == 0) {
                            $properties            = array();
                            $properties ['border'] = 'none';
                            $renderer->_odtTableCellOpenUseProperties($properties);
                            $renderer->tablecell_close();
                        }

                        $properties            = array();
                        $properties ['border'] = 'none';
                        $renderer->_odtTableCellOpenUseProperties($properties);
                        break;
                    default:
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === false) {
                            $renderer->listitem_open(1);
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = true;
                        }
                        break;
                }
                self::$odt_table_stack [self::$odt_table_stack_index - 1]['dtState'] = 0;
                self::$odt_table_stack [self::$odt_table_stack_index - 1]['ddState'] = 1;
                break;
            case 'dt_content_open':
                switch($this->getConf('def_list_odt_export')) {
                    case 'table':
                        $renderer->p_open();
                        break;
                    default:
                        $renderer->listcontent_open();
                        break;
                }
                $this->renderODTOpenSpan($renderer);
                break;
            case 'dd_content_open':
                switch($this->getConf('def_list_odt_export')) {
                    case 'table':
                        $renderer->p_open();
                        break;
                    default:
                        $renderer->listcontent_open();
                        break;
                }
                break;
            case 'dt_content_close':
                $this->renderODTCloseSpan($renderer);
                switch($this->getConf('def_list_odt_export')) {
                    case 'table':
                        $renderer->p_close();
                        break;
                    default:
                        $renderer->listcontent_close();
                        break;
                }
                break;
            case 'dd_content_close':
                switch($this->getConf('def_list_odt_export')) {
                    case 'table':
                        $renderer->p_close();
                        break;
                    default:
                        $renderer->listcontent_close();
                        break;
                }
                break;
            case 'dt_close':
                switch($this->getConf('def_list_odt_export')) {
                    case 'listheader':
                        $renderer->linebreak();
                        break;
                    case 'table':
                        $renderer->tablecell_close();
                        self::$odt_table_stack [self::$odt_table_stack_index - 1]['dtState'] = 2;
                        break;
                    default:
                        $renderer->linebreak();
                        break;
                }
                break;

            case 'dd_close':
                switch($this->getConf('def_list_odt_export')) {
                    case 'listheader':
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === true) {
                            if(method_exists($renderer, 'listheader_close')) {
                                $renderer->listheader_close();
                            } else {
                                $renderer->listitem_close();
                            }
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = false;
                        }
                        break;
                    case 'table':
                        $renderer->tablecell_close();
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === true) {
                            $renderer->tablerow_close(1);
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = false;
                        }
                        break;
                    default:
                        if(self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] === true) {
                            $renderer->listitem_close(1);
                            self::$odt_table_stack [self::$odt_table_stack_index - 1]['itemOpen'] = false;
                        }
                        break;
                }
                self::$odt_table_stack [self::$odt_table_stack_index - 1]['dtState'] = 0;
                self::$odt_table_stack [self::$odt_table_stack_index - 1]['ddState'] = 2;
                break;

            case 'p_open':
                $renderer->p_open();
                break;
            case 'p_close':
                $renderer->p_close();
                break;
        }
    }

    /**
     * Open ODT span for rendering of dt-content
     *
     * @param Doku_Renderer $renderer The current renderer object
     *
     * @author LarsDW223
     */
    private function renderODTOpenSpan($renderer) {
        $properties = array();

        // Get CSS properties for ODT export.
        $renderer->getODTProperties($properties, 'div', 'dokuwiki dt', null);

        $renderer->_odtSpanOpenUseProperties($properties);
    }

    /**
     * Close ODT span for rendering of dt-content
     *
     * @param Doku_Renderer $renderer The current renderer object
     *
     * @author LarsDW223
     */
    private function renderODTCloseSpan($renderer) {
        if(method_exists($renderer, '_odtSpanClose') === false) {
            // Function is not supported by installed ODT plugin version, return.
            return;
        }
        $renderer->_odtSpanClose();
    }
}
