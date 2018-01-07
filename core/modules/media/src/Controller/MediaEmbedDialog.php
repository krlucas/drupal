<?php

namespace Drupal\media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\Entity\Media;

class MediaEmbedDialog extends ControllerBase {

  public function form() {
    $entity = Media::create(['bundle' => 'file', 'uid' => 1]);
    $form = $this->entityFormBuilder()->getForm($entity, 'editor_embed');
    return $form;
  }
}
