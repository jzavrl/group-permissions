<?php

namespace Drupal\group_permissions\Plugin\search_api\processor;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Query\QueryInterface;
use Drupal\group_permissions\Access\AccessManager;
use Drupal\user\Entity\User;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds Group access checks for entities.
 *
 * @SearchApiProcessor(
 *   id = "group_access",
 *   label = @Translation("Group access"),
 *   description = @Translation("Adds Group access checks for entities."),
 *   stages = {
 *     "add_properties" = 0,
 *     "pre_index_save" = -10,
 *     "preprocess_query" = -30,
 *   },
 * )
 */
class GroupAccess extends ProcessorPluginBase {

  use LoggerTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|null
   */
  protected $database;

  /**
   * The current_user service used by this plugin.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|null
   */
  protected $currentUser;

  /**
   * Access manager service.
   *
   * @var \Drupal\group_permissions\Access\AccessManager
   */
  protected $accessManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setLogger($container->get('logger.channel.search_api'));
    $processor->setDatabase($container->get('database'));
    $processor->setCurrentUser($container->get('current_user'));
    $processor->setAccessManager($container->get('group_permissions.access_manager'));

    return $processor;
  }

  /**
   * Retrieves the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  public function getDatabase() {
    return $this->database ?: \Drupal::database();
  }

  /**
   * Sets the database connection.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The new database connection.
   *
   * @return $this
   */
  public function setDatabase(Connection $database) {
    $this->database = $database;
    return $this;
  }

  /**
   * Retrieves the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  public function getCurrentUser() {
    return $this->currentUser ?: \Drupal::currentUser();
  }

  /**
   * Sets the current user.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   *
   * @return $this
   */
  public function setCurrentUser(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
    return $this;
  }

  /**
   * Retrieves the AccessManager service.
   *
   * @return \Drupal\group_permissions\Access\AccessManager
   *   The service.
   */
  public function getAccessManager() {
    return $this->accessManager ?: \Drupal::service('group_permissions.access_manager');
  }

  /**
   * Sets the access manager service.
   *
   * @param \Drupal\group_permissions\Access\AccessManager $access_manager
   *   The service.
   *
   * @return $this
   */
  public function setAccessManager(AccessManager $access_manager) {
    $this->accessManager = $access_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Group IDs'),
        'description' => $this->t('Groups to which the author of this entity belongs to.'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
        'hidden' => TRUE,
        'is_list' => TRUE,
      ];
      $properties['search_api_group_access_ids'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Bundle'),
        'description' => $this->t('Bundle of the entity, needed for Group permission checks.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'hidden' => TRUE,
        'is_list' => FALSE,
      ];
      $properties['search_api_group_access_bundle'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Author'),
        'description' => $this->t('Author of the entity, needed for Group permission checks.'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
        'hidden' => TRUE,
        'is_list' => FALSE,
      ];
      $properties['search_api_group_access_author'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    // Make sure we are running this on an applicable entity.
    $entity_type_id = $item->getDatasource()->getEntityTypeId();
    if (!$this->getAccessManager()->isApplicableEntityType($entity_type_id)) {
      return;
    }

    // Get the entity object.
    /** @var \Drupal\user\EntityOwnerInterface $entity */
    $entity = $this->getEntity($item->getOriginalObject());
    if (!$entity) {
      return;
    }

    // Add the group IDs of which the entity owner is added to.
    // This will make sure we know all groups an entity is a part of through
    // the owner.
    $fields = $item->getFields();
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($fields, NULL, 'search_api_group_access_ids');

    foreach ($fields as $field) {
      if ($author = $this->getUser($entity)) {
        /** @var \Drupal\group\Entity\GroupInterface $group */
        foreach ($this->getAccessManager()->getUserGroups($author) as $group) {
          $field->addValue($group->id());
        }
      }
    }

    // Add the bundle and author of the entity, we need this so that we can
    // properly check against the group permissions (from the
    // GroupContentEnabler) which are based on entity:bundle.
    $fields = $item->getFields();
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($fields, NULL, 'search_api_group_access_bundle');
    foreach ($fields as $field) {
      $field->addValue($entity->bundle());
    }

    $fields = $item->getFields();
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($fields, NULL, 'search_api_group_access_author');
    foreach ($fields as $field) {
      if ($author = $this->getUser($entity)) {
        $field->addValue($author->id());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preIndexSave() {
    $ids_field = $this->ensureField(NULL, 'search_api_group_access_ids', 'integer');
    $ids_field->setHidden();
    $bundle_field = $this->ensureField(NULL, 'search_api_group_access_bundle', 'string');
    $bundle_field->setHidden();
    $author_field = $this->ensureField(NULL, 'search_api_group_access_author', 'integer');
    $author_field->setHidden();
  }

  /**
   * Retrieves the entity related to an indexed search object.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   A search object that is being indexed.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface|mixed|null
   *   The entity related to that search object.
   */
  protected function getEntity(ComplexDataInterface $item) {
    $item = $item->getValue();
    if ($item instanceof EntityInterface) {
      return $item;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    // @TODO this will need to check against the proper options, e.g. Group ones.
    if (!$query->getOption('search_api_bypass_access')) {
      $account = $query->getOption('search_api_access_account', $this->getCurrentUser());
      if (is_numeric($account)) {
        $account = User::load($account);
      }
      if ($account instanceof AccountInterface) {
        $this->addGroupAccess($query, $account);
      }
      else {
        $account = $query->getOption('search_api_access_account', $this->getCurrentUser());
        if ($account instanceof AccountInterface) {
          $account = $account->id();
        }
        if (!is_scalar($account)) {
          $account = var_export($account, TRUE);
        }
        $this->getLogger()
          ->warning('An illegal user UID was given for Group access: @uid.', ['@uid' => $account]);
      }
    }
  }

  /**
   * Adds a node access filter to a search query, if applicable.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to which a node access filter should be added, if applicable.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for whom the search is executed.
   */
  protected function addGroupAccess(QueryInterface $query, AccountInterface $account) {
    // Don't do anything if the user can access all content.
    if ($account->hasPermission('bypass group access checks')) {
      return;
    }

    // Gather the affected datasources, grouped by entity type, as well as the
    // unaffected ones.
    $affected_datasources = [];
    $unaffected_datasources = [];
    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $entity_type = $datasource->getEntityTypeId();
      if ($this->getAccessManager()->isApplicableEntityType($entity_type)) {
        $affected_datasources[$entity_type][] = $datasource_id;
      }
      else {
        $unaffected_datasources[] = $datasource_id;
      }
    }

    // The filter structure we want looks like this:
    // [belongs to other datasource]
    // OR
    //  (
    //   [the user is part of the same group]
    //   AND
    //   [has permission within that group for the bundle]
    // )
    // If there are no "other" datasources, we don't need the nested OR,
    // however, and can add the inner conditions directly to the query.
    if ($unaffected_datasources) {
      $outer_conditions = $query->createConditionGroup('OR', ['group_access']);
      $query->addConditionGroup($outer_conditions);
      foreach ($unaffected_datasources as $datasource_id) {
        $outer_conditions->addCondition('search_api_datasource', $datasource_id);
      }
      $access_conditions = $query->createConditionGroup('OR');
      $outer_conditions->addConditionGroup($access_conditions);
    }
    else {
      $access_conditions = $query;
    }

    // If the user does not have the permission to see any content at all, deny
    // access to all items from affected datasources.
    if (!$affected_datasources) {
      // If there were "other" datasources, the existing filter will already
      // remove all results of entity datasources. Otherwise, we should
      // not return any results at all.
      if (!$unaffected_datasources) {
        $query->abort($this->t('You have no access to any results in this search.'));
      }
      return;
    }

    // We need to get all the Groups of the user viewing the results.
    $user = User::load($account->id());
    $groups = $this->getAccessManager()->getUserGroups($user);

    // First we need to go over all the affected datasources. This shoulds tell
    // us which entity types we need to go over.
    foreach ($affected_datasources as $entity_type => $datasources) {

      // Now, for each of the entities, we also need to go over each of the
      // users Groups.
      /** @var \Drupal\group\Entity\GroupInterface $group */
      foreach ($groups as $group) {
        // Based on the Groups group type, we load all the installed plugins,
        // GroupContentEnablers. This should tell us the entities and bundles
        // that are enabled for a specific Group and with that we can check the
        // permissions against that.
        $group_type = $group->getGroupType();
        $plugins = $this->getAccessManager()->groupContentEnabler->getInstalled($group_type);

        // Now loop over each of the GroupContentEnabler plugin installed on
        // the Group.
        /** @var \Drupal\group\Plugin\GroupContentEnablerBase $item */
        foreach ($plugins->getIterator() as $item) {
          // Only proceed if the entity type of the plugin matches the one from
          // the datasource.
          if ($item->getEntityTypeId() === $entity_type) {
            // Now check for the permissions for the viewing account. The
            // permissions are set inside the Group, based on the
            // GroupContentEnabler. The permission looks like
            // “view entity_type:bundle entity“, and the entity type and bundle
            // we can get from the plugin itself.
            $plugin_id = $item->getPluginId();

            // Get both view any and own permissions.
            $view_own = $group->hasPermission("view own $plugin_id entity", $account);
            $view_any = $group->hasPermission("view $plugin_id entity", $account);

            if ($view_own || $view_any) {
              // The user has permission to view this entity, of this type
              // and he shares the same Group as the author of the entity. So
              // now we create a new AND condition group, add in a condition
              // that this Group ID is in the “group_access_ids“ field, and the
              // “group_access_bundle“ is the same as the bundle from the Group
              // plugin. This should add into a query a condition similar to
              // (indexed entity contains this Group ID and has is of this
              // bundle).
              $bundle = ($item->getEntityBundle()) ? $item->getEntityBundle() : $item->getEntityTypeId();
              $group_conditions = $query->createConditionGroup('AND', ['group_access_group']);
              $group_conditions->addCondition('group_access_ids', $group->id(), 'IN');
              $group_conditions->addCondition('group_access_bundle', $bundle);

              // Additionally if the user only has view own and not view any
              // access, add in the query condition for entity author on the
              // “group_access_author“.
              if ($view_own && !$view_any) {
                $group_conditions->addCondition('group_access_author', $account->id());
              }

              $access_conditions->addConditionGroup($group_conditions);
            }
          }
        }
      }
    }
  }

  /**
   * Gets user from the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool|\Drupal\user\UserInterface
   *   User or FALSE if not found.
   */
  private function getUser(EntityInterface $entity) {
    if ($entity instanceof UserInterface) {
      return $entity;
    }
    elseif ($entity instanceof EntityOwnerInterface) {
      return $entity->getOwner();
    }
    else {
      return FALSE;
    }
  }

}
