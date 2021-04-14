<?php
/*
 * Copyright (c) 2016 Mark C. Prins <mprins@users.sf.net>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */
/**
 * Syntax tests for the yalist plugin.
 *
 * @group plugin_yalist
 * @group plugins
 */
class syntax_plugin_yalist_test extends DokuWikiTest {
    protected $pluginsEnabled = array('yalist');

/**
 * copy data and add pages to the index.
 */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        global $conf;
        $conf['allowdebug'] = 1;
        TestUtils::rcopy(TMP_DIR, dirname(__FILE__) . '/data/');
        dbglog("\nset up class syntax_plugin_yalist_test");
    }
    function setUp(): void {
        parent::setUp();
        global $conf;
        $conf['allowdebug'] = 1;
        $conf['cachetime'] = -1;
        $data = array();
        search($data, $conf['datadir'], 'search_allpages', array('skipacl' => true));
        $verbose = false;
        $force = false;
        foreach ($data as $val) {
            idx_addPage($val['id'], $verbose, $force);
        }
        if ($conf['allowdebug']) {
            touch(DOKU_TMP_DATA . 'cache/debug.log');
        }
    }
    public function tearDown(): void {
        parent::tearDown();
        global $conf;
        // try to get the debug log after running the test, print and clear
        if ($conf['allowdebug']) {
            print "\n";
            readfile(DOKU_TMP_DATA . 'cache/debug.log');
            unlink(DOKU_TMP_DATA . 'cache/debug.log');
        }
    }

    public function testExample(): void {
        $request = new TestRequest();
        $response = $request->get(array('id'=>'example'), '/doku.php');

        // save the response html
        //$handle=fopen('/tmp/data.html', 'w');
        //fwrite($handle, $response->getContent());
        //fclose($handle);

        //print_r($response);
        $this->assertTrue(strpos($response->getContent(),
'<h1 class="sectionedit1" id="yalist_example">yalist example</h1>
<div class="level1">
<ol>
<li class="level1"><div class="li">
 Ordered list item 1
</div></li>
<li class="level1"><div class="li">
 Ordered list item 2
</div></li>
<li class="level1"><div class="li">
<p>
 Ordered list item 3…
</p><p>
 … in multiple paragraphs
</p>
</div></li>
<li class="level1"><div class="li">
 Ordered list item 4
</div></li>
</ol>

<ul>
<li class="level1"><div class="li">
 Unordered list item
</div></li>
<li class="level1"><div class="li">
<p>
 Unordered list item…
</p><p>
 … in multiple paragraphs
</p>
</div></li>
</ul>

<ol>
<li class="level1"><div class="li">
 Ordered list, first level
</div><ol>
<li class="level2"><div class="li">
 Second level
</div><ol>
<li class="level3"><div class="li">
 Third level
</div><ol>
<li class="level4"><div class="li">
 Fourth level
</div></li>
</ol>
</li>
</ol>
</li>
<li class="level2"><div class="li">
<p>
 Back to second level
</p>
</div><ol>
<li class="level3"><div class="li">
 <em>Second?! What happened to third?</em>
</div></li>
</ol>
<div class="li">
<p>
 <em>Quiet, you.</em>
</p>
</div></li>
</ol>
</li>
<li class="level1"><div class="li">
 Back to first level
</div></li>
<li class="level1"><div class="li">
 Still at first level
</div></li>
</ol>

<dl>
<dt class="level1"><span class="dt"> Definition list</span></dt>
<dd class="level1"><div class="dd">
 Definition lists vary only slightly from other types of lists in that list items consist of two parts: a term and a description. The term is given by the DT element and is restricted to inline content. The description is given with a DD element that contains block-level content. [Source: <abbr title="World Wide Web Consortium">W3C</abbr>]
</div></dd>
<dt class="level1"><span class="dt"> Definition list w/ multiple paragraphs</span></dt>
<dd class="level1"><div class="dd">
<p>
 The style sheet provided with this plugin will render these paragraphs…
</p><p>
 … to the left of the term being defined.
</p>
</div><dl>
<dt class="level2"><span class="dt"> Definition list w/ multiple “paragraphs”</span></dt>
<dd class="level2"><div class="dd">
 Another way to separate blocks of text in a definition…
</div></dd>
<dd class="level2"><div class="dd">
 … is to simply have multiple definitions for a term (or group of terms).
</div></dd>
</dl>
</dd>
</dl>

<dl>
<dd class="level1"><div class="dd">
 This definition list has DD tags without any preceding DT tags.
</div></dd>
<dd class="level1"><div class="dd">
 Hey, it&#039;s legal XHTML.
</div></dd>
<dt class="level1"><span class="dt"> Just like DT tags without following DD tags.</span></dt>
<dt class="level1"><span class="dt">? But DT tags can&#039;t contain paragraphs. That would <em class="u">not</em> be legal XHTML.</span></dt>
</dl>

<pre class="code">.. If you try, the result will be rendered oddly.</pre>

</div>') !== false,
            'expected html snippet was not in the output'
        );
    }
}
