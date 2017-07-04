<?php

namespace Sintattica\Atk\Attributes;

/**
 * The LiveTextPreview adds a preview to the page that previews realtime
 * the content of any Attribute or atkTextAttribute while it is being
 * edited.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 */
class LiveTextPreviewAttribute extends DummyAttribute
{
    /**
     * Custom flags.
     */
    const AF_LIVETEXT_SHOWLABEL = DummyAttribute::AF_DUMMY_SHOW_LABEL;
    const AF_LIVETEXT_NL2BR = 67108864;

    public $m_masterattribute = '';

    /**
     * Constructor.
     *
     * @param string $name The name of the attribute
     * @param int $flags Flags for this attribute.
     *                   Use self::AF_LIVETEXT_SHOWLABEL if the preview should be labeled.
     *                   Use self::AF_LIVETEXT_NL2BR if the data should be nl2br'd before display.
     * @param string $masterAttribute The attribute that should be previewed.
     *
     */
    public function __construct($name, $flags = 0, $masterAttribute)
    {
        parent::__construct($name, $flags, '');
        $this->m_masterattribute = $masterAttribute;
    }

    public function edit($record, $fieldprefix, $mode)
    {
        $page = $this->getOwnerInstance()->getPage();
        $id = $this->getHtmlId($fieldprefix);
        $master = $fieldprefix.$this->m_masterattribute;
        $page->register_scriptcode("function {$id}_ReloadTextDiv()
                                  {
                                    var NewText = document.getElementById('{$master}').value;
                                    var DivElement = document.getElementById('{$id}_preview');
                                    ".($this->hasFlag(self::AF_LIVETEXT_NL2BR) ? "NewText = NewText.split(/\\n/).join('<br />');" : '').'
                                    DivElement.innerHTML = NewText;
                                  }                                                                    
                                  ');
        $page->register_loadscript("document.entryform.{$this->m_masterattribute}.onkeyup = {$id}_ReloadTextDiv;");

        return '<span id="'.$id.'_preview">'.$record[$this->m_masterattribute].'</span>';
    }
}
