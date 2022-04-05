<?php

namespace Drupal\group_permissions\Plugin\GroupContentEnabler\Media;

trait GroupMediaTrait {

  /**
   * {@inheritdoc}
   */
  public function getBundleTypeId() {
    return 'media_type';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return 'media';
  }

}
