<?php

namespace Drupal\media\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\SetDialogTitleCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\media\MediaForm;

class MediaFormEmbed extends MediaForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#ajax'] = [
      'callback' => '::ajaxFormRebuild',
    ];
    $form['#prefix'] = '<div id="entity-embed-dialog-form">';
    $form['#suffix'] = '</div>';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxFormRebuild($form, FormStateInterface $form_state) {

    $response = new AjaxResponse();

    // Display errors in form, if any.
    if ($form_state->hasAnyErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = array(
        '#type' => 'status_messages',
        '#weight' => -10,
      );
      $response->addCommand(new HtmlCommand('#entity-embed-dialog-form', $form));
    }
    else {
      // Serialize entity embed settings to JSON string.

      $values = $form_state->getValues();

      $values['attributes'] = [
        'data-embed-button' => 'media',
        'data-entity-embed-display' => 'view_mode:media.full',
        'data-entity-type' => 'media',
        'data-entity-uuid' => $this->getEntity()->uuid(),
      ];
      if (!empty($values['attributes']['data-entity-embed-display-settings'])) {
        $values['attributes']['data-entity-embed-display-settings'] = Json::encode($values['attributes']['data-entity-embed-display-settings']);
      }

      // Filter out empty attributes.
      $values['attributes'] = array_filter($values['attributes'], function($value) {
        return (bool) Unicode::strlen((string) $value);
      });

      // Allow other modules to alter the values before getting submitted to the WYSIWYG.
      $this->moduleHandler->alter('entity_embed_values', $values, $entity, $display, $form_state);

      $response->addCommand(new EditorDialogSave($values));
      $response->addCommand(new CloseModalDialogCommand());
    }

    return $response;
  }

   /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $saved = parent::save($form, $form_state);
    $context = ['@type' => $this->entity->bundle(), '%label' => $this->entity->label()];
    $logger = $this->logger('media');
    $t_args = ['@type' => $this->entity->bundle->entity->label(), '%label' => $this->entity->label()];

    if ($saved === SAVED_NEW) {
      $logger->notice('@type: added %label.', $context);
      drupal_set_message($this->t('@type %label has been created.', $t_args));
    }
    else {
      $logger->notice('@type: updated %label.', $context);
      drupal_set_message($this->t('@type %label has been updated.', $t_args));
    }

    // $form_state->setRedirectUrl($this->entity->toUrl('canonical'));
    return $saved;
  }
  
  public function actionsElement(array $form, FormStateInterface $form_state) {
    $element = parent::actionsElement($form, $form_state);
    $element['submit']['#ajax'] = [
      'callback' => '::ajaxFormRebuild',
    ];
    return $element;
  }

}
