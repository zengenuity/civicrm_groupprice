<?php

require_once 'groupprice.civix.php';

/**
 * Implementation of hook_civicrm_buildForm
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
 */
function groupprice_civicrm_buildForm($formName, &$form) {
  //only add membership list to price option form
  $action = $form->getVar('_action');
  if ($formName == 'CRM_Price_Form_Option' && $action == CRM_Core_Action::UPDATE) {
    //generate list of memberships to add to form
    $group_list = groupprice_getGroupList();

    //get any acl's that already exist
    $oid = $form->getElement('optionId')->getValue();

    //no oid means this price option is being added
    if ($oid) {
      $defaults = groupprice_getAcls($oid);
      $form->setDefaults(array('groupprice_gids' => $defaults['gids']));
    }
    $form->add('select', 'groupprice_gids', 'groupprice_gids', $group_list);
    $element = $form->getElement('groupprice_gids');
    if (!$oid) {
      $element->updateAttributes(array('disabled' => true));
    }
    $element->setMultiple(true);

    //no oid means this price option is being added
    $form->add('checkbox', 'groupprice_negate', 'groupprice_negate');
    if (!empty($defaults)) {
      $form->setDefaults(array('groupprice_negate' => $defaults['negate']));
    }
    $element = $form->getElement('groupprice_negate');
    if (!$oid) {
      $element->updateAttributes(array('disabled' => true));
    }
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postProcess
 */
function groupprice_civicrm_postProcess($formName, &$form) {
  $action = $form->getVar('_action');
  if ($formName == 'CRM_Price_Form_Option' && $action == CRM_Core_Action::UPDATE) {
    $price_option_id = $form->getElement('optionId')->getValue();
    $acl_group_id = $form->getElement('groupprice_gids')->getValue();
    $acl_negate = $form->getElement('groupprice_negate')->getValue();
    if ($price_option_id) {
      //delete any records with this field id first
      $delsql = "DELETE FROM civicrm_groupprice WHERE oid= %1";
      $delparams = array(1 => array($price_option_id, 'Integer'));
      CRM_Core_DAO::executeQuery($delsql, $delparams);
      //if the acl is not set to Everyone, if it is ignore any other options
      foreach ($acl_group_id as $acl_param) {
        //insert new records
        $sql = "INSERT INTO civicrm_groupprice (oid, gid, negate) VALUES (%1, %2, %3)";
        $params = array(1 => array($price_option_id, 'Integer'), 2 => array($acl_param, 'Integer'), 3 => array((int) $acl_negate, 'Integer'));
        CRM_Core_DAO::executeQuery($sql, $params);

      }
    }
  }
}

/**
 * Implementation of hook_civicrm_buildAmount
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildAmount
 */
function groupprice_civicrm_buildAmount($pageType, &$form, &$amount) {

  // get the logged in user id
  $userID = groupprice_civicrm_getUser();

  // First get the static groups
  $isAdmin = FALSE;
  $userGids = array();

  if (!empty($userID)) {
    $params = array(
      'contact_id' => $userID,
      'version' => 3,
    );
    $result = civicrm_api('GroupContact', 'get', $params);
    if (!$result['is_error'] && !empty($result['values'])) {
      foreach ($result['values'] as $group) {
        $userGids[$group['group_id']] = $group['title'];
        if ($group['group_id'] == 1) {
          $isAdmin = TRUE;
        }
      }
    }
  }

  // We will check smart groups as needed.
  $smartGroupsChecked = array();

  foreach ($amount as $amount_id => $priceSetSettings) {
    foreach ($priceSetSettings['options'] as $priceOption) {
      $acl = groupprice_getAcls($priceOption['id']);
      if (empty($acl['gids'])) {
        // No group restrictions.
        continue;
      }

      // Check for smart groups in the list of ACLs.
      if (!empty($userID)) {
        foreach ($acl['gids'] as $gid) {
          if (!in_array($gid, $smartGroupsChecked)) {
            $groupMembership = groupprice_contactIsInGroup($userID, $gid);
            if (!empty($groupMembership)) {
              $userGids += $groupMembership;
            }
            $smartGroupsChecked[$gid] = $gid;
          }
        }
      }

      $hide = FALSE;
      foreach ($acl['gids'] as $gid) {
        if (!$acl['negate']) {
          // Only members of the group can see it.
          if (!array_key_exists($gid, $userGids)) {
            $hide = TRUE;
          }
        }
        else {
          // Negated filtering. Only non-members can see it.
          if (array_key_exists($gid, $userGids)) {
            $hide = TRUE;
          }
        }
      }

      // If the user is an admin, just put a message next to the "hidden" options.
      // Otherwise, really hide them.
      if ($hide) {
        if ($isAdmin) {
          $amount[$amount_id]['options'][$priceOption['id']]['label'] .= '<em class="civicrm-groupprice-admin-message"> (visible by admin access)</em>';
        }
        else {
          $removed = $amount[$amount_id]['options'][$priceOption['id']];
          unset($amount[$amount_id]['options'][$priceOption['id']]);
          if ($removed['is_default'] && !empty($amount[$amount_id]['options'])) {
            $amount[$amount_id]['options'][reset(array_keys($amount[$amount_id]['options']))]['is_default'] = 1;
          }
        }
      }
    }
  }
}

/**
 * Check whether the contact is in the group
 *
 * @param int $contactId
 *   the contact id
 * @param int $groupId
 *   the group id
 * @return mixed
 *   If the user is in the group, returns an array of the group ID and title.
 *   If the user is not in the group, returns FALSE.
 */
function groupprice_contactIsInGroup($contactId, $groupId) {
  $group_info = FALSE;

  // First, determine if this is a smart group.
  // It is a smart group, let's check if user is a member.
  $group_result = civicrm_api('GroupContact', 'get', array(
    'version' => 3,
    'contact_id' => $contactId,
    'group_id' => $groupId
  ));
  if (empty($group_result['is_error']) && !empty($group_result['values'])) {
    foreach ($group_result['values'] as $g) {
      $group_info[$g['group_id']] = $g['title'];
    }
  }
  return $group_info;
}

/**
 * Get a list of all groups for the option list.
 *
 * @return array
 */
function groupprice_getGroupList() {
  $groups = array();

  $params = array(
    'version' => 3,
    'options' => array('limit' => 10000, 'sort' => 'title'),
    'sequential' => 1,
  );
  $result = civicrm_api('group', 'get', $params);

  foreach ($result['values'] as $group) {
    $groups[$group['id']] = $group['title'];

  }
  return $groups;
}

/**
 * Get current group limits for a price option
 *
 * @param $oid
 *   the price option ID.
 * @return array
 */
function groupprice_getAcls($oid) {
  $aces = array('negate' => 0, 'gids' => array()); //access control entries
  $q = "SELECT gid, negate FROM civicrm_groupprice WHERE oid = %1";
  $params = array(1 => array($oid, 'Integer'));
  $dao = CRM_Core_DAO::executeQuery($q, $params);
  while ($dao->fetch()) {
    array_push($aces['gids'], $dao->gid);
    $aces['negate'] = $dao->negate;
  }
  return $aces;
}

/**
 * Gets the current user. Checks for one of the following;
 * cid, select_contact_id, logged in user
 *
 * @return mixed
 * Will return null if nothing is found or ID isn't numeric
 * Will return a integer of either 0 or the user ID
 */
function groupprice_civicrm_getUser() {
	$tempID = CRM_Utils_Request::retrieve('cid', 'Positive');

  // check if there is an On-Behalf Of ID specified. For public and admin use
	$select_contact_id = CRM_Utils_Request::retrieve('select_contact_id', 'Positive');
	if ( !empty( $select_contact_id ) ) {
		$tempID = $select_contact_id;
	}

	// force to ignore the authenticated user
	if ($tempID === '0' || $tempID === 0) {
		// we set the cid on the form so that this will be retained for the Confirm page
		// in the multi-page form & prevent us returning the $userID when this is called
		// from that page
		// we don't really need to set it when $tempID is set because the params have that stored
		return (int) $tempID;
	}

  // Check for logged in user based on session
	$userID = CRM_Core_Session::getLoggedInContactID();

	if (!is_null($tempID) && $tempID === $userID) {
		return (int) $userID;
	}

	// check if this is a checksum authentication
	$userChecksum = CRM_Utils_Request::retrieve('cs', 'String');
	if ($userChecksum) {
		//check for anonymous user.
		$validUser = CRM_Contact_BAO_Contact_Utils::validChecksum($tempID, $userChecksum);
		if ($validUser) {
			return $tempID;
		}
	}
	// check if user has permission, CRM-12062
	elseif ($tempID && CRM_Contact_BAO_Contact_Permission::allow($tempID)) {
		return $tempID;
	}
	return is_numeric($userID) ? $userID : NULL;
}

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function groupprice_civicrm_config(&$config) {
  _groupprice_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function groupprice_civicrm_xmlMenu(&$files) {
  _groupprice_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function groupprice_civicrm_install() {
  $sql = array(
    "CREATE TABLE IF NOT EXISTS `civicrm_groupprice` (
      `oid` int(10) unsigned NOT NULL,
      `gid` int(10) unsigned NOT NULL,
      `negate` int(1) NOT NULL,
      PRIMARY KEY (`oid`,`gid`),
      KEY `oid` (`oid`),
      KEY `gid` (`gid`)
    )",
    "ALTER TABLE `civicrm_groupprice`
      ADD CONSTRAINT `civicrm_groupprice_ibfk_2` FOREIGN KEY (`gid`) REFERENCES `civicrm_group` (`id`),
      ADD CONSTRAINT `civicrm_groupprice_ibfk_1` FOREIGN KEY (`oid`) REFERENCES `civicrm_price_field_value` (`id`);"
  );

  foreach ($sql as $query) {
    $result = CRM_Core_DAO::executeQuery($query);
  }
  return _groupprice_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function groupprice_civicrm_uninstall() {
  CRM_Core_DAO::executeQuery("DROP TABLE civicrm_groupprice;");
  return _groupprice_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function groupprice_civicrm_enable() {
  return _groupprice_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function groupprice_civicrm_disable() {
  return _groupprice_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function groupprice_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _groupprice_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function groupprice_civicrm_managed(&$entities) {
  return _groupprice_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function groupprice_civicrm_caseTypes(&$caseTypes) {
  _groupprice_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function groupprice_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _groupprice_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
