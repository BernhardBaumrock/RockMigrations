<?php
/**
 * RockMigrationsDemoModule Config
 *
 * @author Bernhard Baumrock, 08.01.2019
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
$config = array(
  'helloMessage' => array(
    'type' => 'text',  // can be any Inputfield module name
    'label' => 'Your hello world message',
    'description' => 'This is here as an example of a configurable module property.', 
    'notes' => 'The module can access this value any time from $this->helloMessage.', 
    'value' => 'Hello World', // default value
    'required' => true, 
  ),
  
  'useHello' => array(
    'type' => 'radios',
    'label' => __('Enable hello world message?'), 
    'description' => __('This will make your hello world message display at the bottom of every page.'),
    'notes' => __('The hello message will only be shown to users with edit access to the page.'), 
    'options' => array(
      1 => __('Yes'),
      0 => __('No'),
    ),
    'value' => 0,
  ),
  
);