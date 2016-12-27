<?php

namespace Drupal\autonumber\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'autonumber_default_formatter'.
 *
 * @FieldFormatter(
 *   id = "autonumber_default_formatter",
 *   label = @Translation("Autonumber default"),
 *   field_types = {
 *     "autonumber",
 *   },
 * )
 */
class AutonumberDefaultFormatter extends FormatterBase
{

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $elements = [];
    foreach ($items as $delta => $item)
    {
      // Render output using autonumber_default theme.
      $source = [
        '#theme' => 'autonumber_default',
        '#autonumber_id' => $item->value,
      ];
      $elements[$delta] = [
        '#markup' => \Drupal::service('renderer')->render($source),
      ];
    }
    
    return $elements;
  }
}
