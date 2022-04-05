<?php

namespace Drupal\group_permissions\Plugin\GroupContentEnabler\User;

use Drupal\group_permissions\Plugin\GroupContentEnabler\GroupContentEnablerBase;
use Drupal\group_permissions\Plugin\GroupContentEnabler\GroupContentEnablerInterface;

/**
 * Provides a content enabler for User.
 *
 * @GroupContentEnabler(
 *   id = "group_user",
 *   label = @Translation("Group User"),
 *   description = @Translation("Adds User to groups both publicly and privately."),
 *   entity_type_id = "user",
 *   entity_access = TRUE,
 *   reference_label = @Translation("Title"),
 *   reference_description = @Translation("The title of the User to add to the group")
 * )
 */
class GroupUser extends GroupContentEnablerBase implements GroupContentEnablerInterface {

  /**
   * {@inheritdoc}
   */
  public function getBundleTypeId() {
    return 'user';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return 'user';
  }

}
