<?php

namespace Drupal\group_permissions\Access;

use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides methods for easily extending AccessControlHandlers.
 */
trait AccessControlTrait {

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
   * @param \Drupal\group_permissions\Access\AccessManager $access_manager
   *   Access manager service.
   */
  public function __construct(EntityTypeInterface $entity_type, AccessManager $access_manager) {
    $this->entityTypeId = $entity_type->id();
    $this->entityType = $entity_type;
    $this->accessManager = $access_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('group_permissions.access_manager')
    );
  }

}
