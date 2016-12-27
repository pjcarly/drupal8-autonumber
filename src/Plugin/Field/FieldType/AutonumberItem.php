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
 *   id = "Autonumber",
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
    $properties = [];
    $properties['auto_grouping_pattern'] = DataDefinition::create('string')->setLabel(t('The pattern used for creating the auto grouping.'));
    $properties['auto_grouping'] = DataDefinition::create('string')->setLabel(t('The auto grouping.'));
    $properties['manual_grouping'] = DataDefinition::create('string')->setLabel(t('An extra grouping level that can be set via code.'));
    $properties['created'] = DataDefinition::create('string')->setLabel(t('The timestamp this value was created, the auto grouping is patterns are based off of this value.'));
    $properties['value'] = DataDefinition::create('string')->setLabel(t('The generated number.'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave()
  {
    if($this->shouldUpdateValue())
    {
      $field = $this->getParent()->getName();

      $entity = $this->getEntity();
      $groupingvalue = $this->getGroupingValue();
      $nextValue = $this->getNextValueForGrouping($groupingvalue);

      $entity->$field->value = $nextValue;
      $entity->$field->auto_grouping_pattern = $this->getSetting('auto_grouping_pattern');
      $entity->$field->auto_grouping = $this->getGroupingValue();
    }
    else
    {
      $this->restoreOldValues();
    }
  }

  private function shouldUpdateValue()
  {
    $entity = $this->getEntity();
    $field = $this->getParent()->getName();
    return empty($entity->original) || empty($entity->original->$field->value);
  }

  private function restoreOldValues()
  {
    $entity = $this->getEntity();
    $field = $this->getParent()->getName();
    
    $entity->$field->value = $entity->original->$field->value;
    $entity->$field->auto_grouping_pattern = $entity->original->$field->auto_grouping_pattern;
    $entity->$field->auto_grouping = $entity->original->$field->auto_grouping_pattern;
  }

  private function getGroupingValue()
  {
    $entity = $this->getEntity();
    $year = date('Y', $entity->created->value);
    $quarter = $this->getQuarter($entity->created->value);
    $month = date('m', $entity->created->value);
    $day = date('d', $entity->created->value);

    $groupingPattern = $this->getSetting('auto_grouping_pattern');

    $groupingValue = '';
    $groupingValue .= strstr($groupingPattern, 'YYYY') ? $year : 'YYYY';
    $groupingValue .= '-';
    $groupingValue .= strstr($groupingPattern, 'QQ') ? $quarter : 'QQ';
    $groupingValue .= '-';
    $groupingValue .= strstr($groupingPattern, 'MM') ? $month : 'MM';
    $groupingValue .= '-';
    $groupingValue .= strstr($groupingPattern, 'DD') ? $day : 'DD';

    return $groupingValue;
  }

  private function getQuarter($date)
  {
    return ceil(date('n', $date)/3);
  }

  private function getNextValueForGrouping($autoGrouping = null, $manualGrouping = null)
  {
    // TODO: find a way to handle this thread safe.
    $definition = $this->getFieldDefinition();

    $entity = $definition->getTargetEntityTypeId();
    $fieldName = $definition->getName();

    $query = \Drupal::database()->select($entity.'__'.$fieldName, 'field');
    $query->addExpression('MAX('.$fieldName.'_value)', 'maximumvalue');

    if(!empty($autoGrouping))
    {
      $query->condition('field.'.$fieldName.'_auto_grouping', $autoGrouping);
    }
    if(!empty($manualGrouping))
    {
      $query->condition('field.'.$fieldName.'_manual_grouping', $manualGrouping);
    }

    $currentMaxValue = $query->execute()->fetchField();

    if(empty($currentMaxValue))
    {
      $currentMaxValue = 0;
    }

    // Lets make sure the value is handled as an Integer
    $currentMaxValue = (int) $currentMaxValue;

    // We increase it with 1
    return ++$currentMaxValue;
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

  /**
   * {@inheritdoc}
   */
  public function isEmpty()
  {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }
}
