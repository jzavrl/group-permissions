<?php

namespace Drupal\group_permissions\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Base class for Access Special Case plugins.
 */
abstract class AccessSpecialCaseBase extends PluginBase implements AccessSpecialCaseInterface {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->pluginDefinition['entity_type_id'];
  }

  /**
   * Returns the access manager service.
   *
   * @return \Drupal\group_permissions\Access\AccessManager
   *   Access manager service.
   */
  private function getAccessManager() {
    return \Drupal::service('group_permissions.access_manager');
  }

  /**
   * Determines if the user and the entity belong in the same Group.
   *
   * Determines if the user viewing the entity and the author of that entity
   * belong in the same Group entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user viewing the entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being viewed.
   * @param null|string $type
   *   Type of the Group if we want a more granular search.
   *
   * @return array
   *   Array of shared Group entities.
   */
  public function hasSharedGroups(AccountInterface $account, EntityInterface $entity, $type = NULL) {
    return $this->getAccessManager()->hasSharedGroups($account, $entity, $type);
  }

  /**
   * Get Groups of which a user is a member of.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param null|string $type
   *   Type of the Group if we want a more granular search.
   *
   * @return array
   *   List of Groups as id => Group.
   */
  public function getUserGroups(UserInterface $user, $type = NULL) {
    return $this->getAccessManager()->getUserGroups($user, $type);
  }

  /**
   * Checks permissions within shared groups for the entity and the user.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we are trying to view.
   * @param string $operation
   *   Operation, usually 'view', 'update' and 'delete'.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account trying to access the entity.
   *
   * @return bool
   *   Boolean for access.
   */
  public function checkGroupPermissions(EntityInterface $entity, $operation, AccountInterface $account) {
    return $this->getAccessManager()->checkGroupPermissions($entity, $operation, $account);
  }

}
