<?php


/**
 * HTML class for a checkbox group type field
 *
 * @author Ivan Šakić <ivan.sakic3@gmail.com>
 * @version 0.9b
 * @since PHP4.04pl1
 * @access public
 */
class HTML_QuickForm_checkboxgroup extends HTML_QuickForm_element {

    var $_options = array();

    var $_separator = array();

    var $_values = array();

    function __construct($elementName = null, $elementLabel = null, $options = null,
            $separator = null, $attributes = null) {
        HTML_QuickForm_element::__construct($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        if (count($options) > 1) {
            if (is_array($separator)) {
                $this->_separator = array_splice($separator, 0, count($options) - 1);
            } else {
                $this->_separator = array_fill(0, count($options) - 1, $separator);
            }
        }
        
        $this->_type = 'checkboxgroup';
        if (isset($options)) {
            $this->load($options);
        }
    }

    function load(&$options, $param1 = null, $param2 = null, $param3 = null, $param4 = null) {
        if (is_array($options)) {
            $this->loadArray($options, $param1);
        }
    }

    function getMultiple() {
        return true;
    }

    function setName($name) {
        $this->updateAttributes(array('name' => $name));
    }

    function getName() {
        return $this->getAttribute('name');
    }

    function getPrivateName() {
        return $this->getName() . '[]';
    }

    function setValue($value) {
        if (is_string($value)) {
            $value = preg_split("/[ ]?,[ ]?/", $value);
        }
        if (is_array($value)) {
            $this->_values = array_values($value);
        } else {
            $this->_values = array($value);
        }
    }

    function getValue() {
        return $this->_values;
    }

    function addOption($text, $value, $attributes = null) {
        if (null === $attributes) {
            $attributes = array('value' => $value);
        } else {
            $attributes = $this->_parseAttributes($attributes);
            if (isset($attributes['checked'])) {
                $this->_removeAttr('checked', $attributes);
                if (is_null($this->_values)) {
                    $this->_values = array($value);
                } else if (!in_array($value, $this->_values)) {
                    $this->_values[] = $value;
                }
            }
            $this->_updateAttrArray($attributes, array('value' => $value));
        }
        $this->_options[] = array('text' => $text, 'attr' => $attributes);
    }

    function loadArray($arr, $values = null) {
        if (!is_array($arr)) {
            return self::raiseError('Argument 1 of HTML_Select::loadArray is not a valid array');
        }
        if (isset($values)) {
            $this->setSelected($values);
        }
        foreach ($arr as $key => $val) {
            // Warning: new API since release 2.3
            $this->addOption($val, $key);
        }
        return true;
    }

    function toHtml() {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            $tabs = $this->_getTabs();
            $strHtml = '';
            
            if ($this->getComment() != '') {
                $strHtml .= $tabs . '<!-- ' . $this->getComment() . " //-->\n";
            }
            
            $strHtml .= $tabs;
            $strHtml .= '<input type="hidden" name="' . $this->getName() . '" value="" />';
            
            $i = 0;
            foreach ($this->_options as $option) {
                if (isset($this->_separator[$i])) {
                    $separator = $this->_separator[$i];
                    $i++;
                } else {
                    $separator = '';
                }
                $this->_updateAttrArray($option['attr'], array('name' => $this->getPrivateName()
                ));
                if (is_array($this->_values) &&
                         in_array((string) $option['attr']['value'], $this->_values)) {
                    $this->_updateAttrArray($option['attr'], array('checked' => 'checked'));
                } else {
                    unset($option['attr']['checked']);
                }
                $strHtml .= $tabs . "\t<input type=\"checkbox\"" .
                         $this->_getAttrString($option['attr']) . '/> ' . $option['text'] .
                         "{$separator}\n";
            }
            
            return $strHtml . $tabs;
        }
    }

    function getFrozenHtml() {
        $html = '';
        $i = 0;
        foreach ($this->_options as $option) {
            if (isset($this->_separator[$i])) {
                $separator = $this->_separator[$i];
                $i++;
            } else {
                $separator = '';
            }
            if (array_search($option['attr']['value'], $this->_values) !== false) {
                $box = '<code>[x]</code> ';
            } else {
                $box = '<code>[ ]</code> ';
            }
            $html .= $box . $option['text'] . $separator;
        }
        
        if ($this->_persistantFreeze) {
            $name = $this->getPrivateName();
            // Only use id attribute if doing single hidden input
            if (1 == count($this->_values)) {
                $id = $this->getAttribute('id');
                $idAttr = isset($id) ? array('id' => $id) : array();
            } else {
                $idAttr = array();
            }
            foreach ($this->_values as $value) {
                $html .= '<input' . $this->_getAttrString(
                        array('type' => 'hidden', 'name' => $name, 'value' => $value) + $idAttr) . ' />';
            }
        }
        return $html;
    }

    function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } else if ($value == "") {
            $value = array();
        } else if (!is_array($value)) {
            $value = array($value);
        }
        if (is_array($value) && !empty($this->_options)) {
            $cleanValue = null;
            foreach ($value as $v) {
                for ($i = 0, $optCount = count($this->_options); $i < $optCount; $i++) {
                    if ($v == $this->_options[$i]['attr']['value']) {
                        $cleanValue[] = $v;
                        break;
                    }
                }
            }
        } else {
            $cleanValue = $value;
        }
        return $this->_prepareValue($cleanValue, $assoc);
    }

    function onQuickFormEvent($event, $arg, &$caller) {
        if ('updateValue' == $event) {
            $value = $this->_findValue($caller->_constantValues);
            if (null === $value) {
                $value = $this->_findValue($caller->_submitValues);
                // Fix for bug #4465 & #5269
                // XXX: should we push this to element::onQuickFormEvent()?
                if (null === $value && (!$caller->isSubmitted())) {
                    $value = $this->_findValue($caller->_defaultValues);
                }
            }
            if (null !== $value) {
                $this->setValue($value);
            }
            return true;
        } else {
            return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }
}
