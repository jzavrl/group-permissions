<?php

namespace Drupal\group_permissions\Plugin\GroupContentEnabler;

trait GroupContentEnablerBaseTrait {

  /**
   * {@inheritdoc}
   */
  public function getEntityDefinition() {
    return $this->entityTypeManager->getDefinition($this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleDefinition($bundle = NULL) {
    if ($bundle) {
      return $this->entityTypeManager
        ->getStorage($this->getBundleTypeId())
        ->load($bundle);
    }
    else {
      return $this->entityTypeManager
        ->getStorage($this->getBundleTypeId())
        ->loadMultiple();
    }
  }

}
