<?php namespace ProcessWire;
return [
  // TODO: init callback
  // 'init' => function($rm) {},
  'fields' => [
    'foo' => [
      'type' => 'text',
      'label' => 'foo field label',
      'tags' => 'RMSample',
    ],
    'bar' => [
      'type' => 'textarea',
      'tags' => 'RMSample',
    ],
  ],
  'templates' => [
    'foos' => [
      'childTemplates' => ['foo'],
      'tags' => 'RMSample',
      'icon' => 'bolt',
      'parentTemplates' => ['home'],
      'fields' => ['title'],
    ],
    'bars' => [
      'childTemplates' => ['bar'],
      'tags' => 'RMSample',
      'icon' => 'bolt',
      'parentTemplates' => ['home'],
      'fields' => ['title'],
    ],
    'foo' => [
      'label' => 'foo template',
      'tags' => 'RMSample',
      'icon' => 'check',
      'parentTemplates' => ['foos'],
      'fields' => [
        'foo' => [
          'label' => 'foo label on foo template',
          'columnWidth' => 50,
        ],
        'bar' => [
          'label' => 'bar label on foo template',
          'columnWidth' => 50,
        ],
      ]
    ],
    'bar' => [
      'label' => 'bar template',
      'tags' => 'RMSample',
      'parentTemplates' => ['bars'],
      'fields' => [
        'foo' => [
          'label' => 'foo label on bar template',
          'columnWidth' => 50,
        ],
        'bar' => [
          'label' => 'bar label on bar template',
          'columnWidth' => 50,
        ],
        'images',
      ]
    ],
  ],
  'pages' => [
    // foo pages
    'foos' => [
      'title' => "foos page",
      'template' => "foos",
      'parent' => "/",
      'status' => ['hidden', 'locked'],
    ],
    'foo1' => [
      'template' => "foo",
      'parent' => "/foos",
    ],
    'foo2' => [
      'template' => "foo",
      'parent' => "/foos",
    ],
    
    // bar pages
    'bars' => [
      'title' => "bars page",
      'template' => "bars",
      'parent' => "/",
      'status' => ['hidden', 'locked'],
    ],
    'bar1' => [
      'template' => "bar",
      'parent' => "/bars",
    ],
    'bar2' => [
      'template' => "bar",
      'parent' => "/bars",
    ],
  ],
];
