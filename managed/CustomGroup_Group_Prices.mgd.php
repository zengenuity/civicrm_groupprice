<?php
use CRM_Groupprice_ExtensionUtil as E;

return [
  [
    'name' => 'OptionValue_civicrm_price_field_value',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'cg_extend_objects',
        'label' => E::ts('Price Field Value'),
        'value' => 'PriceFieldValue',
        'name' => 'civicrm_price_field_value',
        'weight' => 5,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Group_Prices',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Group_Prices',
        'title' => E::ts('Group Prices'),
        'extends' => 'PriceFieldValue',
        'style' => 'Inline',
        'help_pre' => E::ts('<p>Use this section to limit the visibility of the price option to members of selected groups.</p>'),
        'collapse_adv_display' => TRUE,
        'is_reserved' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_Group_Prices_groups',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Group_Prices_groups',
        'title' => E::ts('Group Prices :: groups'),
        'data_type' => 'Int',
        'is_reserved' => TRUE,
        'option_value_fields' => [
          'name',
          'label',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_Group_Prices_groups_OptionValue_Ignored_replaced_by_code',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'Group_Prices_groups',
        'label' => E::ts('Ignored - replaced by code'),
        'value' => '1',
        'name' => 'Ignored_replaced_by_code',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Group_Prices_CustomField_groups',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Group_Prices',
        'name' => 'groups',
        'label' => E::ts('Groups'),
        'data_type' => 'Int',
        'html_type' => 'Select',
        'help_post' => E::ts('Limit the price option to be visible only by members of at least one of these groups.'),
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'option_group_id.name' => 'Group_Prices_groups',
        'serialize' => 1,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Group_Prices_CustomField_negate',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Group_Prices',
        'name' => 'negate',
        'label' => E::ts('Negate'),
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
        'default_value' => '0',
        'help_post' => E::ts('If selected, the price option is only visible by users who are not in any of the selected groups.'),
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
