<?php

namespace Drupal\autonumber\FieldProcessor;

use \Drupal\field\Entity\FieldConfig;
use \Drupal\Core\Entity\ContentEntityBase;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Class Processor
 *
 * @package Drupal\autonumber\FieldProcessor
 */
class Processor implements ProcessorInterface
{

  /**
   * @var \Drupal\Core\Entity\ContentEntityBase
   */
  private $entity;

  /**
   * @var \Drupal\field\Entity\FieldConfig
   */
  private $fieldDefinition;

  /**
   * @var string
   */
  private $dateFieldName;

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
  public function getFieldName()
  {
    return $this->fieldDefinition->getName();
  }

  /**
   * @return string
   */
  public function getEntityType()
  {
    return $this->entity->getEntityTypeId();
  }

  /**
   * @return \Drupal\Core\Entity\ContentEntityBase
   */
  public function getEntity()
  {
    return $this->entity;
  }

  /**
   * @param $setting
   *
   * @return mixed
   */
  public function getSetting($setting)
  {
    return $this->fieldDefinition->getSetting($setting);
  }

  /**
   * @return void
   */
  public function process()
  {
    if ($this->shouldUpdateValue()) {
      $field = $this->getFieldName();

      $entity = $this->getEntity();
      $groupingvalue = $this->getGroupingValue();
      $manualGrouping = empty($entity->{$field}->manual_grouping) ? NULL : $entity->{$field}->manual_grouping;
      $nextValue = $this->getNextValueForGrouping($groupingvalue, $manualGrouping);

      $entity->{$field}->value = $nextValue;
      $entity->{$field}->auto_grouping_pattern = $this->getSetting('auto_grouping_pattern');
      $entity->{$field}->auto_grouping = $this->getGroupingValue();
    } else {
      $this->restoreOldValues();
    }
  }

  /**
   * @return bool
   */
  private function shouldUpdateValue()
  {
    $entity = $this->getEntity();
    $field = $this->getFieldName();
    return empty($entity->original) || empty($entity->original->{$field}->value);
  }

  /**
   *
   */
  private function restoreOldValues()
  {
    $entity = $this->getEntity();
    $field = $this->getFieldName();

    $entity->{$field}->value = $entity->original->{$field}->value;
    $entity->{$field}->auto_grouping_pattern = $entity->original->{$field}->auto_grouping_pattern;
    $entity->{$field}->auto_grouping = $entity->original->{$field}->auto_grouping;
  }

  /**
   * Returns the date field to use, to generate the grouping with
   *
   * @return string
   */
  public function getDateField(): string
  {
    return $this->dateFieldName ?? 'created';
  }

  /**
   * Sets the date field to use, to generate the grouping with
   *
   * @return Processor
   */
  public function setDateField(string $value): Processor
  {
    $dateFieldDefinition = $this->entity->getFieldDefinition($value);

    if (!$dateFieldDefinition || !in_array($dateFieldDefinition->getType(), ['changed', 'created', 'timestamp', 'datetime'])) {
      throw new \Exception('Provided custom date field does not exist or is not of a date or timestamp type');
    }

    return $this;
  }

  public function getDateForGroupingPattern(): \DateTimeImmutable
  {
    $datefield = $this->getDateField();
    $dateFieldValue = $this->entity->{$datefield}->value;

    if (empty($dateFieldValue)) {
      throw new \Exception('Value of the provided date field is empty');
    }

    $returnValue = null;
    $dateFieldDefinition = $this->entity->getFieldDefinition($datefield);
    switch ($dateFieldDefinition->getType()) {
      case 'changed':
      case 'created':
      case 'timestamp':
        $returnValue = \DateTimeImmutable::createFromFormat('U', $dateFieldValue, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        break;
      case 'datetime':
        $fieldSettingsDatetimeType = $dateFieldDefinition->getItemDefinition()->getSettings()['datetime_type'];
        if ($fieldSettingsDatetimeType === 'date') {
          $returnValue = \DateTimeImmutable::createFromFormat(DateTimeItemInterface::DATE_STORAGE_FORMAT, $dateFieldValue, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        } else if ($fieldSettingsDatetimeType === 'datetime') {
          $returnValue = \DateTimeImmutable::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $dateFieldValue, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        }
        break;
      default:
        throw new \Exception('Unsupported date field type');
    }

    return $returnValue;
  }

  /**
   * @return string
   */
  private function getGroupingValue()
  {
    $date = $this->getDateForGroupingPattern();

    $year = $date->format('Y');
    $quarter = ceil($date->format('n') / 3);
    $month = $date->format('m');
    $day = $date->format('d');

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
