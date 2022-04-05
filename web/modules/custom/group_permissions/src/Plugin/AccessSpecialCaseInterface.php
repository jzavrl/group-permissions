<?php

namespace Drupal\group_permissions\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for Access Special Case plugins.
 */
interface AccessSpecialCaseInterface extends PluginInspectionInterface {

  /**
   * Determines if access special case should be granted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being operated on.
   * @param string $operation
   *   Operation, could be 'view', 'update' or 'delete'.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account performing the operation.
   *
   * @return bool
   *   Boolean if any exceptions grant access.
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account);

}
