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
