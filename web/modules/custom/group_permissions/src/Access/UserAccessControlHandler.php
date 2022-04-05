<?php

namespace Drupal\group_permissions\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserAccessControlHandler as UserAccess;

/**
 * Access controller for the User entity.
 *
 * @see \Drupal\user\Entity\User
 */
class UserAccessControlHandler extends UserAccess implements EntityHandlerInterface {

  use AccessControlTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Below are special use cases for the User entity, taken from the original
    // UserAccessControlHandler.
    // We don't treat the user label as privileged information, so this check
    // has to be the first one in order to allow labels for all users to be
    // viewed, including the special anonymous user.
    if ($operation === 'view label') {
      return AccessResult::allowed();
    }

    // The anonymous user's profile can neither be viewed, updated nor deleted.
    if ($entity->isAnonymous()) {
      return AccessResult::forbidden();
    }

    // Administrators can view/update/delete all user profiles.
    if ($account->hasPermission('administer users')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Separate permissions for deleting own account.
    if ($operation === 'delete' && $account->id() == $entity->id()) {
      return AccessResult::allowedIfHasPermission($account, 'cancel account')->cachePerUser();
    }

    // If the entity is applicable for the Group permissions, run the checks.
    if ($this->accessManager->isApplicable($entity)) {
      return $this->accessManager->checkAccess($entity, $operation, $account);
    }

    // The entity is not applicable for custom Group access checks,
    // revert back to the default checkAccess method.
    return parent::checkAccess($entity, $operation, $account);
  }

}
