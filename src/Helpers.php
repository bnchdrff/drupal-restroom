<?php
/**
 * @file
 * API-related helper methods cribbed from commerce_services.
 */

namespace Bnchdrff\DrupalRestroom;

/**
 * Defines static helper methods that are useful for building nice APIs.
 */
class Helpers {

  /**
   * As seen in commerce_services: flatten fields.
   *
   * To make things easier to use, we:
   *   * flatten fields (remove i18n & change multiple fields to arrays)
   *   * output taxonomy term references as [{tid:'tid',name:'name'},{...}]
   *     or just {tid:'tid',name:'name'}
   *
   * The original docs:
   *
   * Flattens field value arrays on the given entity.
   *
   * Field flattening in Commerce Services involves reducing their value arrays
   * to just the current language of the entity and reducing fields with single
   * column schemas to simple scalar values or arrays of scalar values.
   *
   * Note that because this function irreparably alters an entity's structure,
   * it should only be called using a clone of the entity whose field value
   * arrays should be flattened. Otherwise the flattening will affect the entity
   * as stored in the entity cache, causing potential errors should that entity
   * be loaded and manipulated later in the same request.
   *
   * @param string $entity_type
   *   The machine-name entity type of the given entity.
   * @param object $cloned_entity
   *   A clone of the entity whose field value arrays should be flattened.
   */
  public static function flattenFields($entity_type, $cloned_entity) {
    $bundle = field_extract_bundle($entity_type, $cloned_entity);
    $clone_wrapper = entity_metadata_wrapper($entity_type, $cloned_entity);
    // Loop over every field instance on the given entity.
    foreach (field_info_instances($entity_type, $bundle) as $field_name => $instance) {
      $field_info = field_info_field($field_name);

      // Add file & image URLs.
      if (in_array($field_info['type'], array('file', 'image'))) {
        $url_field_name = $field_name . '_url';

        // Set the URL for a single value field.
        if ($field_info['cardinality'] == 1) {
          $field_value = $clone_wrapper->{$field_name}->raw();
          $cloned_entity->{$url_field_name} = '';
          $url = NULL;

          // If the field value contains a URI...
          if (!empty($field_value['uri'])) {
            // And we can generate a URL to the file at that URI...
            $url = file_create_url($field_value['uri']);

            if (!empty($url)) {
              // Add it to the entity using the URL field name.
              $cloned_entity->{$url_field_name} = $url;
            }
          }
        }
        else {
          // Otherwise loop over the field and generate each URL.
          $cloned_entity->{$url_field_name} = array();

          foreach ($clone_wrapper->{$field_name}->getIterator() as $delta => $field_wrapper) {
            $field_value = $field_wrapper->raw();
            $url = NULL;

            // If the field value contains a URI...
            if (!empty($field_value['uri'])) {
              // And we can generate a URL to the file at that URI...
              $url = file_create_url($field_value['uri']);

              if (!empty($url)) {
                // Add it to the entity using the URL field name.
                $cloned_entity->{$url_field_name}[$delta] = $url;
              }
            }

            // If the field value did not have a URI or the URL to the file could not
            // be determined, add an empty URL string to the entity.
            if (empty($url)) {
              $cloned_entity->{$url_field_name}[$delta] = '';
            }
          }
        }
      }

      $field_info = field_info_field($field_name);
      // Add file & image URLs.
      if (in_array($field_info['type'], array('file', 'image'))) {
        $url_field_name = $field_name . '_url';
        // Set the URL for a single value field.
        if ($field_info['cardinality'] == 1) {
          $field_value = $clone_wrapper->{$field_name}->raw();
          $cloned_entity->{$url_field_name} = '';
          $url = NULL;
          // If the field value contains a URI...
          if (!empty($field_value['uri'])) {
            // And we can generate a URL to the file at that URI...
            $url = file_create_url($field_value['uri']);
            if (!empty($url)) {
              // Add it to the entity using the URL field name.
              $cloned_entity->{$url_field_name} = $url;
            }
          }
        }
        else {
          // Otherwise loop over the field and generate each URL.
          $cloned_entity->{$url_field_name} = array();
          foreach ($clone_wrapper->{$field_name}->getIterator() as $delta => $field_wrapper) {
            $field_value = $field_wrapper->raw();
            $url = NULL;
            // If the field value contains a URI...
            if (!empty($field_value['uri'])) {
              // And we can generate a URL to the file at that URI...
              $url = file_create_url($field_value['uri']);
              if (!empty($url)) {
                // Add it to the entity using the URL field name.
                $cloned_entity->{$url_field_name}[$delta] = $url;
              }
            }
            // If the field value did not have a URI or the URL to the file
            // could not be determined, add an empty URL string to the entity.
            if (empty($url)) {
              $cloned_entity->{$url_field_name}[$delta] = '';
            }
          }
        }
      }
      // Set the field property to the raw wrapper value, which applies the
      // desired flattening of the value array.
      // For taxonomy term refs, format nicely using loadtermnames module 'name'
      if ($clone_wrapper->{$field_name}->type() == 'taxonomy_term') {
        $term = $clone_wrapper->{$field_name}->value();
        // Explicitly set single-value taxonomy_term fields as null, rather than
        // an empty array.
        if (count($cloned_entity->{$field_name}) == 0) {
          $cloned_entity->{$field_name} = NULL;
        }
        if ($cloned_entity->{$field_name}) {
          $cloned_entity->{$field_name} = ['tid' => $term->tid, 'name' => $term->name];
        }
      }
      elseif ($clone_wrapper->{$field_name}->type() == 'list<taxonomy_term>') {
        $new_val = [];
        foreach ($clone_wrapper->{$field_name}->value() as $term) {
          $new_val[] = ['tid' => $term->tid, 'name' => $term->name];
        }
        $cloned_entity->{$field_name} = $new_val;
      }
      elseif ($clone_wrapper->{$field_name}->type() == 'list<text>') {
        $opts = $clone_wrapper->{$field_name}->optionsList();
        $newval = [];
        $iter = 0;
        foreach ($clone_wrapper->{$field_name}->value() as $machine_val) {
          $label = $opts[$machine_val];
          $newval[$iter]['machine_name'] = $machine_val;
          // For fields with only labels, set both as the same thing.
          $newval[$iter]['label'] = ($label) ? $label : $machine_val;
          $iter++;
        }
        unset($iter);
        $cloned_entity->{$field_name} = $newval;
        unset($newval);
      }
      elseif ($clone_wrapper->{$field_name}->type() == 'text' && $clone_wrapper->{$field_name}->label()) {
        $cloned_entity->{$field_name} = [
          'machine_name' => $clone_wrapper->{$field_name}->value(),
          'label' => $clone_wrapper->{$field_name}->label(),
        ];
      }
      else {
        $cloned_entity->{$field_name} = $clone_wrapper->{$field_name}->raw();
      }
    }
  }

  /**
   * As seen in commerce_services...
   *
   * Returns a list of properties for the specified entity type.
   *
   * For the purpose of the Commerce Services module, the properties returned
   * are those that correspond to a database column as determined by the Entity
   * API. These may be used to filter and sort index queries.
   *
   * @param string $entity_type
   *   Machine-name of the entity type whose properties should be returned.
   *
   * @return array
   *   An associative array of properties for the specified entity type with the
   *   key being the property name and the value being the corresponding schema
   *   field on the entity type's base table.
   */
  public static function entityTypeProperties($entity_type) {
    $properties = drupal_static(__FUNCTION__);
    if (!isset($properties[$entity_type])) {
      $entity_info = entity_get_info($entity_type);
      $info = entity_get_property_info($entity_type);
      $properties[$entity_type] = array();
      // Loop over only the properties of the entity type.
      foreach ($info['properties'] as $key => $value) {
        // If the value specifies a schema field...
        if (!empty($value['schema field'])) {
          $properties[$entity_type][$key] = $value['schema field'];
        }
      }
      // If the entity type supports revisions, add revision and log to the
      // array of acceptable properties.
      if (!empty($entity_info['revision table'])) {
        $properties[$entity_type] += array('revision', 'log');
      }
    }
    return $properties[$entity_type];
  }

  /**
   * As seen in commerce_services...
   *
   * Returns a list of fields for the specified entity type.
   *
   * @param string $entity_type
   *   Machine-name of the entity type whose properties should be returned.
   * @param string $bundle
   *   Optional bundle name to limit the returned fields to.
   *
   * @return array
   *   An associative array of fields for the specified entity type with the key
   *   being the field name and the value being the Entity API property type.
   */
  public static function entityTypeFields($entity_type, $bundle = NULL) {
    $fields = drupal_static(__FUNCTION__);
    if (!isset($fields[$entity_type])) {
      $info = entity_get_property_info($entity_type);
      $fields = array();
      // Loop over the bundles info to inspect their fields.
      foreach ($info['bundles'] as $bundle_name => $bundle_info) {
        // Loop over the properties on the bundle to find field information.
        foreach ($bundle_info['properties'] as $key => $value) {
          if (!empty($value['field'])) {
            $fields[$entity_type][$bundle_name][$key] = $value['type'];
          }
        }
      }
    }
    // If a specific bundle's fields was requested, return just those.
    if (!empty($bundle)) {
      return $fields[$entity_type][$bundle];
    }
    else {
      // Otherwise combine all the fields for various bundles of the entity type
      // into a single return value.
      $combined_fields = array();
      foreach ($fields[$entity_type] as $bundle_name => $bundle_fields) {
        $combined_fields += $bundle_fields;
      }
      return $combined_fields;
    }
  }

  /**
   * As seen in commerce_services: filtering.
   *
   * Adds property and field conditions to an index EntityFieldQuery.
   *
   * @param \EntityFieldQuery $query
   *   The EntityFieldQuery object being built for the index query.
   * @param string $entity_type
   *   Machine-name of the entity type of the index query.
   * @param array $filter
   *   An associative array of property names, single column field names, or
   *   multi-column field column names with their values to use to filter the
   *   result set of the index request.
   * @param array $filter_op
   *   An associative array of field and property names with the operators to
   *   use when applying their filter conditions to the index request query.
   */
  public static function indexQueryFilter(\EntityFieldQuery $query, $entity_type, array $filter, array $filter_op) {
    // Loop over each filter field to add them as property or field conditions
    // on the query object. This function assumes the $filter and $filter_op
    // arrays contain matching keys to set the correct operator to the filter
    // fields.
    foreach ($filter as $filter_field => $filter_value) {
      // Determine the corresponding operator for this filter field, defaulting
      // to = in case of an erroneous request.
      $operator = '=';
      if (!empty($filter_op[$filter_field])) {
        $operator = $filter_op[$filter_field];
      }
      // If operator is IN, try to turn the filter into an array.
      if ($operator == 'IN') {
        $filter_value = explode(',', $filter_value);
      }
      // If the current filter field is a property, use a property condition.
      $properties = self::entityTypeProperties($entity_type);
      if (in_array($filter_field, array_keys($properties), TRUE)) {
        $query->propertyCondition($properties[$filter_field], $filter_value, $operator);
      }
      else {
        // Look for the field name among the entity type's field list.
        foreach (self::entityTypeFields($entity_type) as $field_name => $field_type) {
          // If the filter field begins with a field name, then either the
          // filter field is the field name or is a column of the field.
          if (strpos($filter_field, $field_name) === 0) {
            $field_info = field_info_field($field_name);
            // If field is list_boolean, convert true => 1 and false => 0.
            if ($field_info['type'] == 'list_boolean') {
              if ($filter_value === 'true') {
                $filter_value = 1;
              }
              if ($filter_value === 'false') {
                $filter_value = 0;
              }
            }
            // If it is the field name and the field type has a single column
            // schema, add the field condition to the index query.
            if ($field_name == $filter_field && count($field_info['columns']) == 1) {
              $column = key($field_info['columns']);
              $query->fieldCondition($field_name, $column, $filter_value, $operator);
              break;
            }
            else {
              // Otherwise if the filter field contains a valid column
              // specification for the field type, add the field condition to
              // the index query.
              $column = substr($filter_field, strlen($field_name) + 1);
              if (in_array($column, array_keys($field_info['columns']))) {
                $query->fieldCondition($field_name, $column, $filter_value, $operator);
                break;
              }
            }
          }
        }
      }
    }
  }

  /**
   * As seen in commerce_services: sorting.
   *
   * Adds property and field order by directions to an index EntityFieldQuery.
   *
   * @param \EntityFieldQuery $query
   *   The EntityFieldQuery object being built for the index query.
   * @param string $entity_type
   *   Machine-name of the entity type of the index query.
   * @param array $sort_by
   *   An array of database fields to sort the query by, with sort fields being
   *   valid properties, single column field names, or multi-column field column
   *   names for the matching entity type.
   * @param array $sort_order
   *   The corresponding sort orders for the fields specified in the $sort_by
   *   array; one of either 'DESC' or 'ASC'.
   */
  public static function indexQuerySort(\EntityFieldQuery $query, $entity_type, array $sort_by, array $sort_order) {
    // Loop over each sort field to add them as property or field order by
    // directions on the query object. This function assumes the $sort_by and
    // $sort_order arrays contain an equal number of elements with keys matching
    // the sort field to the appropriate sort order.
    foreach ($sort_by as $sort_key => $sort_field) {
      // Determine the corresponding sort direction for this sort field,
      // defaulting to DESC in case of an erroneous request.
      $direction = 'DESC';
      if (!empty($sort_order[$sort_key])) {
        $direction = strtoupper($sort_order[$sort_key]);
      }
      // If the current sort field is a property, use a property condition.
      $properties = self::entityTypeProperties($entity_type);
      if (in_array($sort_field, array_keys($properties), TRUE)) {
        $query->propertyOrderBy($properties[$sort_field], $direction);
      }
      else {
        // Look for the field name among the entity type's field list.
        foreach (self::entityTypeFields($entity_type) as $field_name => $field_type) {
          // If the sort field begins with a field name, then either the sort
          // field is the field name or is a column of the field.
          if (strpos($sort_field, $field_name) === 0) {
            $field_info = field_info_field($field_name);
            // If it is the field name and the field type has a single column
            // schema, add the field condition to the index query.
            if ($field_name == $sort_field && count($field_info['columns']) == 1) {
              $column = key($field_info['columns']);
              $query->fieldOrderBy($field_name, $column, $direction);
              break;
            }
            else {
              // Otherwise if the sort field contains a valid column
              // specification for the field type, add the field condition to
              // the index query.
              $column = substr($sort_field, strlen($field_name) + 1);
              if (in_array($column, array_keys($field_info['columns']))) {
                $query->fieldOrderBy($field_name, $column, $direction);
                break;
              }
            }
          }
        }
      }
    }
  }

}
