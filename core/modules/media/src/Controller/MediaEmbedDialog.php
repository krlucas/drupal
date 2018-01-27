<?php

namespace Drupal\media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RequestContext;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MediaEmbedDialog extends ControllerBase {

  public function form($editor, $media_type) {
    $entity = Media::create(['bundle' => $media_type, 'uid' => $this->currentUser()->id()]);
    $form = $this->entityFormBuilder()->getForm($entity, 'editor_embed');
    return $form;
  }

}
