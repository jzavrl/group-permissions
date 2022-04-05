<?php

namespace Drupal\group_permissions\Plugin\GroupContentEnabler;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base deriver class for GroupContentEnabler.
 *
 * @package Drupal\group_permissions\Plugin\GroupContentEnabler
 */
abstract class GroupContentEnablerDeriverBase extends DeriverBase implements ContainerDeriverInterface {

  use GroupContentEnablerBaseTrait;

  /**
   * The base plugin ID this derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an GroupContentEnablerDeriverBase object.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(
    $base_plugin_id,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->getBundleDefinition() as $name => $types) {
      $label = $types->label();

      $this->derivatives[$name] = [
        'entity_bundle' => $name,
        'label' => t('Group @type (@bundle)', ['@type' => $this->getEntityDefinition()->getLabel(), '@bundle' => $label]),
        'description' => t('Adds %type entity to groups both publicly and privately.', ['%type' => $label]),
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
