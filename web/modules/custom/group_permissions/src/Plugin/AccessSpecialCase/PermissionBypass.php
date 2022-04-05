<?php

namespace Drupal\group_permissions\Plugin\AccessSpecialCase;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group_permissions\Plugin\AccessSpecialCaseBase;

/**
 * Provides an access special case if the user has bypass permission.
 *
 * @AccessSpecialCase(
 *   id = "permission_bypass",
 *   label = @Translation("Permission bypass"),
 *   type = "allowed",
 *   entity_type_id = {},
 *   excluded_entity_type_id = {},
 * )
 */
class PermissionBypass extends AccessSpecialCaseBase {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return $account->hasPermission('bypass group access checks');
  }

}
