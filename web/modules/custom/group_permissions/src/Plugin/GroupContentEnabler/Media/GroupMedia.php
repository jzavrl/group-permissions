<?php

namespace Drupal\group_permissions\Plugin\GroupContentEnabler\Media;

use Drupal\group_permissions\Plugin\GroupContentEnabler\GroupContentEnablerBase;
use Drupal\group_permissions\Plugin\GroupContentEnabler\GroupContentEnablerInterface;

/**
 * Provides a content enabler for media.
 *
 * @GroupContentEnabler(
 *   id = "group_media",
 *   label = @Translation("Group media"),
 *   description = @Translation("Adds media to groups both publicly and privately."),
 *   entity_type_id = "media",
 *   entity_access = TRUE,
 *   reference_label = @Translation("Title"),
 *   reference_description = @Translation("The title of the media to add to the group"),
 *   deriver = "Drupal\group_permissions\Plugin\GroupContentEnabler\Media\GroupMediaDeriver"
 * )
 */
class GroupMedia extends GroupContentEnablerBase implements GroupContentEnablerInterface {

  use GroupMediaTrait;

}
