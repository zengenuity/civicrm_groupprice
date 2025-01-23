<?php

use CRM_Groupprice_ExtensionUtil as E;

class CRM_Groupprice_Upgrader extends CRM_Extension_Upgrader_Base {

  public function upgrade_2000(): bool {
    $this->ctx->log->info('Applying update 2000: Ensure managed entities are loaded');

    // Reconcile the managed entities
    // Enables custom fields on price field values and creates custom group

    // Need to force a rebuild of the cached mixins to notice the new managed files.
    // See Civi\Core\Container::boot()
    \CRM_Extension_System::singleton()->getMixinLoader()->run(TRUE);

    // Only need to reconcile our entities
    \Civi\Api4\Managed::reconcile(FALSE)
      ->setModules(['civicrm_groupprice'])
      ->execute();

    // Clear file cache to get rid of old cached template override
    \CRM_Core_Config::singleton()->cleanup(1);

    return TRUE;
  }

  public function upgrade_2001(): bool {
    $this->ctx->log->info('Applying update 2001: Convert custom table to custom fields');

    // based on old groupprice_getAcls($oid)
    $dao = \CRM_Core_DAO::executeQuery("SELECT oid, gid, negate FROM civicrm_groupprice");
    while ($dao->fetch()) {
      $acls[$dao->oid]['gids'][] = $dao->gid;
      $acls[$dao->oid]['negate'] = $dao->negate;
    }

    foreach ($acls as $price_option_id => $price_option_values) {
      \Civi\Api4\PriceFieldValue::update(FALSE)
        ->addWhere('id', '=', $price_option_id)
        ->addValue('Group_Prices.groups', $price_option_values['gids'])
        ->addValue('Group_Prices.negate', $price_option_values['negate'])
        ->execute();
    }

    \CRM_Core_DAO::executeQuery("DROP TABLE civicrm_groupprice");

    return TRUE;
  }

}
