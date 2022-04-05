<?php

namespace Drupal\group_permissions\Access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler as NodeAccess;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for the node entities.
 *
 * @see \Drupal\node\NodeAccessControlHandler.
 */
class NodeAccessControlHandler extends NodeAccess {

  /**
   * Drupal\group_permissions\Access\AccessManager definition.
   *
   * @var \Drupal\group_permissions\Access\AccessManager
   */
  protected $accessManager;

  /**
   * Constructs a NodeAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\node\NodeGrantDatabaseStorageInterface $grant_storage
   *   The node grant storage.
   * @param \Drupal\group_permissions\Access\AccessManager $access_manager
   *   Access manager service.
   */
  public function __construct(EntityTypeInterface $entity_type, NodeGrantDatabaseStorageInterface $grant_storage, AccessManager $access_manager) {
    parent::__construct($entity_type, $grant_storage);
    $this->accessManager = $access_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('node.grant_storage'),
      $container->get('group_permissions.access_manager')
    );
  }

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
