dokuwiki-yalist-plugin
======================

This plugin extends DokuWiki's list markup syntax to allow definition lists
and list items with multiple paragraphs. The complete syntax is as follows:

```
  - ordered list item            [<ol><li>]  <!-- as standard syntax -->
  * unordered list item          [<ul><li>]  <!-- as standard syntax -->
  ? definition list term         [<dl><dt>]
  : definition list definition   [<dl><dd>]

  -- ordered list item w/ multiple paragraphs
  ** unordered list item w/ multiple paragraphs
  :: definition list definition w/multiple paragraphs
  .. new paragraph in --, **, or ::
```

Lists can be nested within lists, just as in the standard DokuWiki syntax.


[![CI](https://github.com/mprins/dokuwiki-yalist-plugin/actions/workflows/CI.yml/badge.svg)](https://github.com/mprins/dokuwiki-yalist-plugin/actions/workflows/CI.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mprins/dokuwiki-yalist-plugin/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mprins/dokuwiki-yalist-plugin/?branch=master)
[![GitHub issues](https://img.shields.io/github/issues/mprins/dokuwiki-yalist-plugin.svg)](https://github.com/mprins/dokuwiki-yalist-plugin/issues)
[![GitHub forks](https://img.shields.io/github/forks/mprins/dokuwiki-yalist-plugin.svg)](https://github.com/mprins/dokuwiki-yalist-plugin/network)
[![GitHub stars](https://img.shields.io/github/stars/mprins/dokuwiki-yalist-plugin.svg)](https://github.com/mprins/dokuwiki-yalist-plugin/stargazers)
[![GitHub license](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://raw.githubusercontent.com/mprins/dokuwiki-yalist-plugin/master/LICENSE)
