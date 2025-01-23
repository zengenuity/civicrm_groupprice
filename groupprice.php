<?php

require_once 'groupprice.civix.php';

/**
 * Implements hook_civicrm_fieldOptions().
 *
 * Change the options of Group_Prices.groups to be a list of all groups
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_fieldOptions/
 */
function groupprice_civicrm_fieldOptions(string $entity, string $field, ?array &$options, array $params): void {
  if ($entity == 'PriceFieldValue') {
    if (substr($field, 0, 6) == 'custom') {
      // Old style custom_N field
      $field = CRM_Core_BAO_CustomField::getLongNameFromShortName($field);
    }
    if ($field == "Group_Prices.groups") {
      $groups = \Civi\Api4\Group::get(FALSE)
        ->addSelect('title')
        ->addWhere('is_active', '=', TRUE)
        ->addOrderBy('title', 'ASC')
        ->execute();
      foreach ($groups as $group) {
        $options[$group['id']] = $group['title'];
      }
    }
  }
}

/**
 * Implements hook_civicrm_buildAmount().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildAmount/
 */
function groupprice_civicrm_buildAmount($pageType, &$form, &$amount): void {
  $userID = $form->getContactID();
  $isAdmin = CRM_Core_Permission::check('administer CiviCRM');

  // Load all the non-empty Group_Prices custom fields
  $priceFieldValueCustomFields = (array) \Civi\Api4\PriceFieldValue::get(FALSE)
    ->addSelect('Group_Prices.*')
    ->addWhere('Group_Prices.groups', 'IS NOT NULL')
    ->execute()
    ->indexBy('id');

  // Iterate through $amount structure
  foreach ($amount as $amount_id => $priceSetSettings) {
    foreach (array_keys($priceSetSettings['options']) as $id) {
      $customFields = $priceFieldValueCustomFields[$id] ?? NULL;
      // Skip if no Group_Price custom fields set
      if (!$customFields) {
        continue;
      }

      $groups = $customFields['Group_Prices.groups'] ?? [];
      // Skip if no Groups configured
      if (!$groups) {
        continue;
      }

      // See if user is in any of the configured groups
      $inAnyGroup = (bool) \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id')
        ->addWhere('id', '=', $userID)
        ->addWhere('groups', 'IN', $groups)
        ->execute()
        ->count();

      $negate = $customFields['Group_Prices.negate'] ?? FALSE;

      // Normally, we hide if user is not in any of the groups
      // When negating, we hide if user is in any of the groups
      $hide = $negate ? $inAnyGroup : !$inAnyGroup;

      // If the user is an admin, just put a message next to the "hidden" options.
      // Otherwise, really hide them.
      if ($hide) {
        if ($isAdmin) {
          $amount[$amount_id]['options'][$id]['label'] .= '<em class="civicrm-groupprice-admin-message"> (visible by admin access)</em>';
        }
        else {
          unset($amount[$amount_id]['options'][$id]);
        }
      }
    }
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function groupprice_civicrm_config(&$config): void {
  _groupprice_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install/
 */
function groupprice_civicrm_install(): void {
  _groupprice_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable/
 */
function groupprice_civicrm_enable(): void {
  _groupprice_civix_civicrm_enable();
}
