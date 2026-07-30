<?php
/**
 * Field partial: default input (text-like types, incl. date/check_in/check_out
 * which render as text inputs enhanced by flatpickr).
 *
 * @var array  $f           Field definition.
 * @var string $input_class Extra CSS class for date-type inputs ('' otherwise).
 * @var string $field_id    Unique input id (label[for] target).
 * @var string $error_id    Id of the inline error element (aria-describedby).
 * @var bool   $is_required Whether the field is required.
 */
?>
<?php $input_type = in_array($f['type'], ['date', 'check_in', 'check_out']) ? 'text' : $f['type']; ?>
                                    <input type="<?php echo esc_attr($input_type); ?>" class="<?php echo esc_attr($input_class); ?>" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($f['name']); ?>" placeholder="<?php echo esc_attr(!empty($f['placeholder']) ? $f['placeholder'] : $f['label']); ?>"<?php if ( $is_required ) echo ' aria-required="true"'; ?> aria-describedby="<?php echo esc_attr($error_id); ?>">
