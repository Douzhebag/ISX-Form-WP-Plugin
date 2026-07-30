<?php
/**
 * Field partial: textarea.
 *
 * @var array  $f           Field definition.
 * @var string $field_id    Unique textarea id (label[for] target).
 * @var string $error_id    Id of the inline error element (aria-describedby).
 * @var bool   $is_required Whether the field is required.
 */
?>
<textarea id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($f['name']); ?>" rows="4" placeholder="<?php echo esc_attr(!empty($f['placeholder']) ? $f['placeholder'] : $f['label']); ?>"<?php if ( $is_required ) echo ' aria-required="true"'; ?> aria-describedby="<?php echo esc_attr($error_id); ?>"></textarea>
