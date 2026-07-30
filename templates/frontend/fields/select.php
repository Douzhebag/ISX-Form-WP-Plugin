<?php
/**
 * Field partial: select (dropdown).
 *
 * @var array  $f             Field definition.
 * @var array  $options_array Parsed dropdown options.
 * @var string $field_id      Unique select id (label[for] target).
 * @var string $error_id      Id of the inline error element (aria-describedby).
 * @var bool   $is_required   Whether the field is required.
 */
?>
<select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($f['name']); ?>"<?php if ( $is_required ) echo ' aria-required="true"'; ?> aria-describedby="<?php echo esc_attr($error_id); ?>">
                                        <option value="">-- <?php echo esc_html__( 'Please select', 'insightx-form' ); ?> --</option>
                                        <?php foreach($options_array as $opt): if(empty($opt)) continue; ?>
                                            <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
