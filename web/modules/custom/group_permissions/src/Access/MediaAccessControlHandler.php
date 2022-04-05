<?php

namespace Drupal\group_permissions\Access;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaAccessControlHandler as MediaAccess;

/**
 * Access controller for the Media entity.
 *
 * @see \Drupal\media\Entity\Media.
 */
class MediaAccessControlHandler extends MediaAccess implements EntityHandlerInterface {

  use AccessControlTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // If the entity is applicable for the Group permissions, run the checks.
    if ($this->accessManager->isApplicable($entity)) {
      return $this->accessManager->checkAccess($entity, $operation, $account);
    }

    // The entity is not applicable for custom Group access checks,
    // revert back to the default checkAccess method.
    return parent::checkAccess($entity, $operation, $account);
  }

}
