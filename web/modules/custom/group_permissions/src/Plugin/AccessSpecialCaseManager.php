<?php

namespace Drupal\group_permissions\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Access Special Case plugin manager.
 */
class AccessSpecialCaseManager extends DefaultPluginManager {

  /**
   * Constructs a new AccessSpecialCaseManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AccessSpecialCase', $namespaces, $module_handler, 'Drupal\group_permissions\Plugin\AccessSpecialCaseInterface', 'Drupal\group_permissions\Annotation\AccessSpecialCase');

    $this->alterInfo('group_permissions_access_special_case_info');
    $this->setCacheBackend($cache_backend, 'group_permissions_access_special_case_plugins');
  }

  /**
   * Loads all plugins created for a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $type
   *   Type of the plugin to search for.
   *
   * @return array
   *   Array of plugins.
   */
  public function getEntitySpecialCases($entity_type_id, $type) {
    $definitions = [];
    /** @var \Drupal\group_permissions\Plugin\AccessSpecialCaseBase $definition */
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if ($definition['type'] == $type) {
        // Give back all plugins that match the entity type id.
        $has_entity_type = in_array($entity_type_id, $definition['entity_type_id']);

        // We also need to include plugins which don't have entity type defined.
        $has_all_entity_types = empty($definition['entity_type_id']) && !isset($definitions[$plugin_id]);

        // There can be an option to exclude the plugin from an entity type.
        $exclude_entity_type = in_array($entity_type_id, $definition['excluded_entity_type_id']);

        if (($has_entity_type || $has_all_entity_types) && !$exclude_entity_type) {
          $definitions[$plugin_id] = $definition;
        }
      }
    }

    return $definitions;
  }

}
