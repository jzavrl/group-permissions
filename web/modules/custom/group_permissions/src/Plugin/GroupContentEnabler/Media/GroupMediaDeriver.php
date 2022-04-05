<?php

namespace Drupal\group_permissions\Plugin\GroupContentEnabler\Media;

use Drupal\group_permissions\Plugin\GroupContentEnabler\GroupContentEnablerDeriverBase;
use Drupal\group_permissions\Plugin\GroupContentEnabler\GroupContentEnablerInterface;

/**
 * Class GroupMediaDeriver.
 *
 * @package Drupal\group_permissions\Plugin\GroupContentEnabler
 */
class GroupMediaDeriver extends GroupContentEnablerDeriverBase implements GroupContentEnablerInterface {

  use GroupMediaTrait;

}
