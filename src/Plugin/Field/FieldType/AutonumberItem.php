<?php

namespace Drupal\autonumber\Plugin\Field\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
//use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\Sql\DefaultTableMapping;


/**
 * Plugin implementation of the 'Autonumber' field type.
 *
 * @FieldType(
 *   id = "autonumber",
 *   label = @Translation("Autonumber"),
 *   module = "field_example",
 *   description = @Translation("Automatic generated number field."),
 *   default_widget = "autonumber_default_widget",
 *   default_formatter = "autonumber_default_formatter"
 * )
 */
class AutonumberItem extends FieldItemBase implements FieldItemInterface
{
  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings()
  {
    return [
      'auto_grouping_pattern' => DRUPAL_OPTIONAL
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition)
  {
    // dump('2');die;
    return [
      'columns' => [
        'auto_grouping_pattern' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'auto_grouping' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'manual_grouping' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'value' => [
          'type' => 'int',
          'length' => 32,
        ],
      ],
      'indexes' => [
        'auto_grouping' => ['auto_grouping'],
        'manual_grouping' => ['manual_grouping'],
        'value' => ['value'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
  {
    // dump('3');die;
    $properties = [];
    $properties['auto_grouping_pattern'] = DataDefinition::create('string')->setLabel(t('The pattern used for creating the auto grouping.'));
    $properties['auto_grouping'] = DataDefinition::create('string')->setLabel(t('The auto grouping.'));
    $properties['manual_grouping'] = DataDefinition::create('string')->setLabel(t('An extra grouping level that can be set via code.'));
    $properties['value'] = DataDefinition::create('integer')->setLabel(t('The generated number.'))->setComputed(TRUE)->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state)
  {
    $element = array();

    $element['auto_grouping_pattern'] = array(
      '#type' => 'textfield',
      '#title' => t('Grouping Pattern'),
      '#default_value' => $this->getSetting('auto_grouping_pattern'),
      '#description' => t('Patterns available YYYY, QQ, MM, DD'),
    );

    return $element;
  }
}
