<?php

namespace Drupal\group_permissions\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Drupal\group_permissions\Plugin\AccessSpecialCaseManager;
use Drupal\user\Entity\User;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Handles custom access logic management.
 */
class AccessManager {

  use StringTranslationTrait;

  /**
   * List of entities => handlers that need to be updated.
   */
  const HANDLERS = [
    'node' => NodeAccessControlHandler::class,
    'user' => UserAccessControlHandler::class,
    'media' => MediaAccessControlHandler::class,
  ];

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Drupal\group\Plugin\GroupContentEnablerManagerInterface definition.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  public $groupContentEnabler;

  /**
   * Drupal\group_permissions\Plugin\AccessSpecialCaseManager definition.
   *
   * @var \Drupal\group_permissions\Plugin\AccessSpecialCaseManager
   */
  protected $specialCaseManager;

  /**
   * Drupal\group\GroupMembershipLoaderInterface definition.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * If message debugging is enabled.
   *
   * @var bool
   */
  private $messageDebugging;

  /**
   * If log debugging is enabled.
   *
   * @var bool
   */
  private $logDebugging;

  /**
   * Constructs a new AccessHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger service.
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $group_content_enabler
   *   Group content enabler service.
   * @param \Drupal\group_permissions\Plugin\AccessSpecialCaseManager $special_case_manager
   *   Access special case service.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   Membership loader service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger,
    GroupContentEnablerManagerInterface $group_content_enabler,
    AccessSpecialCaseManager $special_case_manager,
    GroupMembershipLoaderInterface $membership_loader,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('group_permissions');
    $this->groupContentEnabler = $group_content_enabler;
    $this->specialCaseManager = $special_case_manager;
    $this->membershipLoader = $membership_loader;
    $this->config = $config_factory->get('group_permissions.toggle_permissions_debugging');
    $this->messenger = $messenger;

    $this->messageDebugging = $this->config->get('enable_message_debugging');
    $this->logDebugging = $this->config->get('enable_log_debugging');
  }

  /**
   * Gets the entity types on which GroupContent is enabled.
   *
   * @return array
   *   Array of entity types.
   */
  public function getEntityTypes() {
    $entities = [];
    foreach ($this->getPluginDefinitions() as $id => $plugin) {
      $entities[] = key($plugin);
    }
    return $entities;
  }

  /**
   * Gets the bundles by entities on which GroupContent is enabled.
   *
   * @param string $entity_type
   *   Entity type for which to get the bundles.
   *
   * @return array
   *   Array of bundles.
   */
  public function getEntityBundles($entity_type) {
    foreach ($this->getPluginDefinitions() as $id => $plugin) {
      if (isset($plugin[$entity_type])) {
        return $plugin[$entity_type];
      }
    }

    return [];
  }

  /**
   * Get the new access handler class for an entity type.
   *
   * @param string $entity_type
   *   The entity type for which to get the new access handler.
   *   Can be empty to provide all.
   *
   * @return array|mixed
   *   Array of all access handlers or just the one for a specific entity type.
   */
  public function getEntityAccessHandler($entity_type = NULL) {
    return self::HANDLERS[$entity_type] ?? self::HANDLERS;
  }

  /**
   * Check if the entity is applicable for our custom access logic.
   *
   * If the entity and bundle is added as the GroupContent than that bundle is
   * applicable for the custom logic. Otherwise the access handler will use the
   * original parent checkAccess method.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object which is being viewed.
   *
   * @return bool
   *   If the entity is applicable for custom logic.
   */
  public function isApplicable(EntityInterface $entity) {
    return in_array($entity->bundle(), $this->getEntityBundles($entity->getEntityTypeId()));
  }

  /**
   * Check if the entity type is applicable for Group access logic.
   *
   * @param string $entity_type
   *   Entity type to check on.
   *
   * @return bool
   *   If the entity is applicable for custom logic.
   */
  public function isApplicableEntityType($entity_type) {
    return in_array($entity_type, $this->getEntityTypes());
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
    // @TODO this might need to change depending on how the entity stores its author.
    if ($entity instanceof UserInterface) {
      $author = $entity;
    }
    else {
      /** @var \Drupal\user\EntityOwnerInterface $entity */
      $author = $entity->getOwner();
    }

    // Get the user entity from the account.
    $user = User::load($account->id());

    // Get all groups of the user.
    $view_groups = $this->getUserGroups($user, $type);
    $entity_groups = $this->getUserGroups($author, $type);

    // If the two arrays intersect, that means they share some Groups.
    return array_intersect_key($view_groups, $entity_groups);
  }

  /**
   * The main logic for access checks based on the Groups.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we are trying to view.
   * @param string $operation
   *   Operation, usually 'view', 'update' and 'delete'.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account trying to access the entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The appropriate AccessResult response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Check for forbidden special cases.
    if ($this->checkSpecialCases($entity, $operation, $account, 'forbidden')) {
      return $this->access(FALSE, $entity);
    }

    // Check for allowed special cases.
    if ($this->checkSpecialCases($entity, $operation, $account, 'allowed')) {
      return $this->access(TRUE, $entity);
    }

    $access = $this->checkGroupPermissions($entity, $operation, $account);

    return $this->access($access, $entity);
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
    // Check if the author and the user viewing have any common groups.
    if ($groups = $this->hasSharedGroups($account, $entity)) {
      // Get the GroupContentEnabler plugin ID. We need this because Group
      // permissions are defined from this.
      $plugin_id = $this->getPluginDefinition($entity)->getPluginId();

      // We need to loop through all of the Groups that both users are in.
      /** @var \Drupal\group\Entity\GroupInterface $group */
      foreach ($groups as $group) {
        $permitted = FALSE;
        switch ($operation) {
          // Permissions are different based on entity status and operation.
          // We are just checking in the user has permission and saving that to
          // act on it later.
          case 'view':
          case 'download':
            // Determine if the entity is published or active. Defaults to TRUE.
            if ($entity instanceof EntityPublishedInterface) {
              $is_published = $entity->isPublished();
            }
            elseif ($entity instanceof UserInterface) {
              $is_published = $entity->isActive();
            }
            else {
              $is_published = TRUE;
            }

            if ($is_published) {
              $permission = "view $plugin_id entity";
              $permitted = $group->hasPermission($permission, $account);

              if (!$permitted) {
                // Check for specific view own entity permission. First get the
                // correct owner ID from the entity.
                $owner_id = NULL;
                if ($entity instanceof EntityOwnerInterface) {
                  /** @var \Drupal\user\EntityOwnerInterface $entity */
                  $owner_id = $entity->getOwnerId();
                }
                elseif ($entity instanceof UserInterface) {
                  $owner_id = $entity->id();
                }

                // Now if the IDs match, meaning the viewing account is also the
                // owner, then check for the permissions.
                if ($owner_id == $account->id()) {
                  $permission = "view own $plugin_id entity";
                  $permitted = $group->hasPermission($permission, $account);
                }
              }
            }
            else {
              $permission = "view unpublished $plugin_id entity";
              $permitted = $group->hasPermission($permission, $account);
            }
            break;

          case 'update':
          case 'delete':
            $permission = "$operation any $plugin_id entity";
            $permitted = $group->hasPermission($permission, $account);
            break;
        }

        // We've been checking for permissions on the Group. Only return if
        // there was a permission granted, otherwise continue. This is because
        // we need to check all of the Groups and stop only once we get a grant.
        if ($permitted) {
          if ($this->messageDebugging || $this->logDebugging) {
            $message = $this->t('Group Access allowed access for entity %label for a %operation operation, because of a %permission permission in %group_type type Group called %group_label.', [
              '%label' => $entity->label(),
              '%operation' => $operation,
              '%permission' => $permission,
              '%group_type' => $group->bundle(),
              '%group_label' => $group->label(),
            ]);
            $this->showDebuggingMessage($message);
          }

          return TRUE;
        }
      }

      if ($this->messageDebugging || $this->logDebugging) {
        $message = $this->t('Group Access forbid access for entity %label for a %operation operation, as no group granted permission for it.', [
          '%label' => $entity->label(),
          '%operation' => $operation,
        ]);
        $this->showDebuggingMessage($message);
      }

      return FALSE;
    }
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
    $groups = [];

    /** @var \Drupal\group\GroupMembership $membership */
    foreach ($this->membershipLoader->loadByUser($user) as $membership) {
      $group = $membership->getGroup();

      if ($type && $group->bundle() === $type) {
        $groups[$group->id()] = $group;
      }
      elseif ($type === NULL) {
        $groups[$group->id()] = $group;
      }
    }

    return $groups;
  }

  /**
   * Get all GroupContent plugin definitions that are installed.
   *
   * @return array
   *   List of the plugins installed grouped by entity types and bundles within.
   *
   * @throws \Exception
   */
  protected function getPluginDefinitions() {
    $plugins = [];

    /** @var \Drupal\group\Plugin\GroupContentEnablerBase $item */
    foreach ($this->groupContentEnabler->getInstalled()->getIterator() as $item) {
      $entity = $item->getEntityTypeId();
      $plugins[$item->getBaseId()][$entity][] = $item->getEntityBundle() ?: $entity;
    }

    return $plugins;
  }

  /**
   * Get plugin definition based on a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool|\Drupal\group\Plugin\GroupContentEnablerBase
   *   GroupContentEnabler plugin definition or FALSE if none found.
   *
   * @throws \Exception
   */
  protected function getPluginDefinition(EntityInterface $entity) {
    /** @var \Drupal\group\Plugin\GroupContentEnablerBase $item */
    foreach ($this->groupContentEnabler->getInstalled()->getIterator() as $item) {
      // Group membership will show up here when searching for User entity, but
      // we should skip it and get to our own implementation.
      if ($item->getBaseId() === 'group_membership') {
        continue;
      }

      if ($item->getEntityBundle()) {
        // We have a bundled entity type so we need to check against the bundle
        // as well.
        if ($item->getEntityTypeId() == $entity->getEntityTypeId() && $item->getEntityBundle() == $entity->bundle()) {
          return $item;
        }
      }
      else {
        // No bundles in this entity type, entity type check is enough.
        if ($item->getEntityTypeId() == $entity->getEntityTypeId()) {
          return $item;
        }
      }
    }

    return FALSE;
  }

  /**
   * Returns AccessResult with proper cache context.
   *
   * @param bool $access
   *   Boolean for allowed or forbidden access.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is being viewed.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   AccessResult response.
   */
  private function access($access, EntityInterface $entity) {
    $result = ($access) ? AccessResult::allowed() : AccessResult::forbidden();
    return $result
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($entity);
  }

  /**
   * Checks for access special cases.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being operated on.
   * @param string $operation
   *   Operation, could be 'view', 'update' or 'delete'.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account performing the operation.
   * @param string $type
   *   Type of the exception, can be 'allowed' or 'forbidden'.
   *
   * @return bool
   *   Boolean if any special cases grant access.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function checkSpecialCases(EntityInterface $entity, $operation, AccountInterface $account, $type) {
    // Load all AccessSpecialCase plugins for this entity type.
    $special_cases = $this->specialCaseManager->getEntitySpecialCases($entity->getEntityTypeId(), $type);

    foreach ($special_cases as $plugin_id => $plugin_definition) {
      // Run access check on the plugin.
      /** @var \Drupal\group_permissions\Plugin\AccessSpecialCaseInterface $access_special_case */
      $access_special_case = $this->specialCaseManager->createInstance($plugin_id);
      if ($access_special_case->checkAccess($entity, $operation, $account)) {
        if ($this->messageDebugging || $this->logDebugging) {
          $message = $this->t('Special Case Access %plugin plugin of %type acted on entity %label for a %operation operation and returned %return', [
            '%plugin' => $access_special_case->getPluginId(),
            '%type' => $type,
            '%label' => $entity->label(),
            '%operation' => $operation,
            '%return' => 'TRUE',
          ]);
          $this->showDebuggingMessage($message);
        }

        return TRUE;
      }

      if ($this->messageDebugging || $this->logDebugging) {
        $message = $this->t('Special Case Access %plugin plugin of %type acted on entity %label for a %operation operation and returned %return', [
          '%plugin' => $access_special_case->getPluginId(),
          '%type' => $type,
          '%label' => $entity->label(),
          '%operation' => $operation,
          '%return' => 'FALSE',
        ]);
        $this->showDebuggingMessage($message);
      }
    }
    return FALSE;
  }

  /**
   * Shows a debugging message based on the settings.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message to show.
   */
  private function showDebuggingMessage(TranslatableMarkup $message) {
    if ($this->messageDebugging) {
      $this->messenger->addMessage($message, $this->messenger::TYPE_STATUS, TRUE);
    }
    if ($this->logDebugging) {
      $this->logger->info($message);
    }
  }

}
