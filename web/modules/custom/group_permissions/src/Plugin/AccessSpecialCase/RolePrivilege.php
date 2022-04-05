<?php

namespace Drupal\group_permissions\Plugin\AccessSpecialCase;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group_permissions\Plugin\AccessSpecialCaseBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an access special case to check role privilege.
 *
 * Users can view/edit/delete other user accounts based on group permissions.
 * But that doesn't take into account the role weights for privilege. Roles
 * higher on the list can perform operations on the ones below it.
 *
 * @AccessSpecialCase(
 *   id = "role_privilege",
 *   label = @Translation("Role privilege"),
 *   type = "forbidden",
 *   entity_type_id = {
 *     "user",
 *   },
 *   excluded_entity_type_id = {},
 * )
 */
class RolePrivilege extends AccessSpecialCaseBase implements ContainerFactoryPluginInterface {

  /**
   * List of roles to not check against.
   *
   * @var array
   */
  const DISABLED_ROLES = [
    'anonymous',
    'authenticated',
  ];

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * List of all roles in the system.
   *
   * @var array
   */
  private $roles;

  /**
   * Constructs a new SameHospital object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Plugin should only work for update operation.
    if ($operation !== 'update') {
      return FALSE;
    }

    // Prepare the full list of roles in the system to check against.
    $all_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach (self::DISABLED_ROLES as $disabled_role) {
      unset($all_roles[$disabled_role]);
    }
    $this->roles = array_keys($all_roles);

    // We need the weight of the user viewing and the user being viewed.
    /** @var \Drupal\user\UserInterface $entity */
    $user_role_weight = $this->getHighestRole($account->getRoles());
    $entity_role_weight = $this->getHighestRole($entity->getRoles());

    // If the user viewing highest role is still lower than the highest role of
    // the viewed user, then we need to forbid access.
    return $user_role_weight > $entity_role_weight;
  }

  /**
   * Finds the highest role from the list.
   *
   * @param array $roles
   *   List of roles to check against.
   *
   * @return int
   *   The role weight.
   */
  private function getHighestRole(array $roles) {
    // Set some high number, so we will definitely find a higher role.
    $role_weight = 100;

    // We need to go over all the roles to make sure we find the highest one.
    foreach ($roles as $role) {
      // Do not check against disabled roles.
      if (in_array($role, self::DISABLED_ROLES)) {
        continue;
      }

      // Get the weight from the full list of roles in the system and compare it
      // to the previously set weight.
      $weight = (int) array_search($role, $this->roles);
      if ($weight < $role_weight) {
        $role_weight = $weight;
      }
    }

    return $role_weight;
  }

}
