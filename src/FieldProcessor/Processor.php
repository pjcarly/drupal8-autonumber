<?php
namespace Drupal\autonumber\FieldProcessor;

use \Drupal\field\Entity\FieldConfig;
use \Drupal\Core\Entity\ContentEntityBase;

/**
 * Class Processor
 *
 * @package Drupal\autonumber\FieldProcessor
 */
class Processor {

  /**
   * @var \Drupal\Core\Entity\ContentEntityBase
   */
  private $entity;

  /**
   * @var \Drupal\field\Entity\FieldConfig
   */
  private $fieldDefinition;

  /**
   * Processor constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   * @param \Drupal\field\Entity\FieldConfig $fieldDefinition
   */
  public function __construct(
    ContentEntityBase $entity,
    FieldConfig $fieldDefinition
  ) {
    $this->entity = $entity;
    $this->fieldDefinition = $fieldDefinition;
  }

  /**
   * @return string
   */
  public function getFieldName() {
    return $this->fieldDefinition->getName();
  }

  /**
   * @return string
   */
  public function getEntityType() {
    return $this->entity->getEntityTypeId();
  }

  /**
   * @return \Drupal\Core\Entity\ContentEntityBase
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * @param $setting
   *
   * @return mixed
   */
  public function getSetting($setting) {
    return $this->fieldDefinition->getSetting($setting);
  }

  /**
   *
   */
  public function process() {
    if ($this->shouldUpdateValue()) {
      $field = $this->getFieldName();

      $entity = $this->getEntity();
      $groupingvalue = $this->getGroupingValue();
      $manualGrouping = empty($entity->$field->manual_grouping) ? NULL : $entity->$field->manual_grouping;
      $nextValue = $this->getNextValueForGrouping($groupingvalue, $manualGrouping);

      $entity->$field->value = $nextValue;
      $entity->$field->auto_grouping_pattern = $this->getSetting('auto_grouping_pattern');
      $entity->$field->auto_grouping = $this->getGroupingValue();
    }
    else {
      $this->restoreOldValues();
    }
  }

  /**
   * @return bool
   */
  private function shouldUpdateValue() {
    $entity = $this->getEntity();
    $field = $this->getFieldName();
    return empty($entity->original) || empty($entity->original->$field->value);
  }

  /**
   *
   */
  private function restoreOldValues() {
    $entity = $this->getEntity();
    $field = $this->getFieldName();

    $entity->$field->value = $entity->original->$field->value;
    $entity->$field->auto_grouping_pattern = $entity->original->$field->auto_grouping_pattern;
    $entity->$field->auto_grouping = $entity->original->$field->auto_grouping;
  }

  /**
   * @return string
   */
  private function getGroupingValue() {
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

  /**
   * @param $date
   *
   * @return float
   */
  private function getQuarter($date) {
    return ceil(date('n', $date)/3);
  }

  /**
   * @param null $autoGrouping
   * @param null $manualGrouping
   *
   * @return int
   */
  private function getNextValueForGrouping(
    $autoGrouping = NULL,
    $manualGrouping = NULL
  ) {
    // TODO: find a way to handle this thread safe.
    $entityType = $this->getEntityType();
    $fieldName = $this->getFieldName();

    $query = \Drupal::database()->select($entityType . '__' . $fieldName, 'field');
    $query->addExpression('MAX(' . $fieldName . '_value)', 'maximumvalue');

    if (!empty($autoGrouping)) {
      $query->condition('field.' . $fieldName . '_auto_grouping', $autoGrouping);
    }

    if (!empty($manualGrouping)) {
      $query->condition('field.' . $fieldName . '_manual_grouping', $manualGrouping);
    }

    $currentMaxValue = $query->execute()->fetchField();

    if (empty($currentMaxValue)) {
      $currentMaxValue = 0;
    }

    // Lets make sure the value is handled as an Integer
    $currentMaxValue = (int) $currentMaxValue;

    // We increase it with 1
    return ++$currentMaxValue;
  }

}
