<?php

namespace Drupal\group_permissions\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Access Special Case item annotation object.
 *
 * @see \Drupal\group_permissions\Plugin\AccessSpecialCaseManager
 * @see plugin_api
 *
 * @Annotation
 */
class AccessSpecialCase extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The access type, can be 'allowed' or 'forbidden'.
   *
   * @var string
   */
  public $type;

  /**
   * The entity type ID.
   *
   * @var array
   */
  public $entity_type_id;

  /**
   * The excluded entity type ID.
   *
   * @var array
   */
  public $excluded_entity_type_id;

}
