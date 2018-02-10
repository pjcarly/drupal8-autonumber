<?php

namespace Drupal\autonumber\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'autonumber_default' widget.
 *
 * @FieldWidget(
 *   id = "autonumber_default_widget",
 *   label = @Translation("Hidden (Automatic)"),
 *   field_types = {
 *     "autonumber"
 *   }
 * )
 */
class AutonumberDefaultWidget extends WidgetBase
{

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $element['value'] = array(
    // Understand number (integer)
      '#type' => 'hidden',
      // Default value cannot be NULL,
      // throws 'This value should be of the correct primitive type'.
      // @see https://www.drupal.org/node/2220381
      // so the autonumber is defaulted to a positive int.
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : 1,
    );
    return $element;
  }
}
