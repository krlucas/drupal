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

/**
 * Form controller for the media embed add/edit forms.
 *
 * @internal
 */
class MediaFormEmbed extends MediaForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#ajax'] = [
      'callback' => '::ajaxFormRebuild',
    ];
    $form['#prefix'] = '<div id="media-embed-dialog-form">';
    $form['#suffix'] = '</div>';
    $input = $form_state->getUserInput();
    $editor_dom_id = '';
    if (!empty($input['editor_object'])) {
      $editor_dom_id = $input['editor_object']['editor-id'];
    }
    elseif (!empty($input['editor_dom_id'])) {
      $editor_dom_id = $input['editor_id'];
    }
    $form['editor_dom_id'] = [
      '#type' => 'hidden',
      '#value' => $editor_dom_id,
    ];
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
      $response->addCommand(new HtmlCommand('#media-embed-dialog-form', $form));
    }
    else {
      // Embed the entity element in the editor and close the dialog.
      $values = $form_state->getValues();
      $values['attributes'] = [
        'data-embed-button' => 'media',
        'data-entity-embed-display' => 'view_mode:media.full',
        'data-entity-type' => 'media',
        'data-entity-uuid' => $this->getEntity()->uuid(),
      ];

      // Filter out empty attributes.
      $values['attributes'] = array_filter($values['attributes'], function($value) {
        return (bool) Unicode::strlen((string) $value);
      });

      $response->addCommand(new EditorDialogSave($values));
      $response->addCommand(new CloseModalDialogCommand());
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function actionsElement(array $form, FormStateInterface $form_state) {
    $element = parent::actionsElement($form, $form_state);
    $element['submit']['#ajax'] = [
      'callback' => '::ajaxFormRebuild',
    ];
    return $element;
  }

}
