<?php namespace ProcessWire;
/**
 * Tools for the PW backend
 *
 * @author Bernhard Baumrock, 10.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class RockAdminTools extends WireData implements Module, ConfigurableModule {

  public static function getModuleInfo() {
    return [
      'title' => 'RockAdminTools',
      'version' => '0.0.2',
      'summary' => 'Tools for the PW backend',
      'autoload' => true,
      'singular' => true,
      'icon' => 'bolt',
      'requires' => [],
      'installs' => [],
    ];
  }

  public function init() {
    $this->addHook("InputfieldWrapper::fieldset", $this, "wrapFieldsIntoFieldset");
  }

  /**
   * Add a fieldset to the form and add listed Inputfields
   *
   * Usage:
   * $form->fieldset([
   *   'label' => 'My Fieldset',
   *   'fields' => [
   *      'foo' => ['label' => 'foo field', 'columnWidth' => 33],
   *      'bar' => ['label' => 'bar field', 'columnWidth' => 33],
   *   ],
   * ]);
   *
   * If a listed field does not exist in the form it will be skipped silently
   *
   * @return void
   */
  public function wrapFieldsIntoFieldset(HookEvent $event) {
    $wrapper = $event->object; /** @var InputfieldWrapper $wrapper */
    $data = $event->arguments(0);
    $fields = array_key_exists('fields', $data) ? $data['fields'] : [];
    unset($data['fields']);

    /** @var InputfieldFieldset $fs */
    $fs = $this->wire('modules')->get('InputfieldFieldset');
    foreach($data as $k=>$v) $fs->$k = $v;

    // where to add the fieldset?
    if($before = $event->arguments(1)) {
      $field = $wrapper->get((string)$before);
      if($field) $wrapper->insertBefore($fs, $field);
    }
    elseif($after = $event->arguments(2)) {
      $field = $wrapper->get((string)$after);
      if($field) $wrapper->insertAfter($fs, $field);
    }
    else $wrapper->add($fs);

    // add fields
    foreach($fields as $k=>$v) {
      if(is_int($k)) {
        // field was applied as simple string
        $f = $wrapper->get($v);
      }
      else {
        // we got a field plus custom settings
        // this makes it easy to adjust columnWidth, label, etc
        $f = $wrapper->get($k);
        if($f) {
          // set all dynamic properties
          foreach($v as $prop=>$val) $f->$prop = $val;
        }
      }

      if($f) {
        // add field to fieldset and remove it from the form
        $wrapper->remove($f);
        $fs->add($f);
      }
    }

    // return fieldset
    $event->return = $fs;
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    return $inputfields;
  }
}
