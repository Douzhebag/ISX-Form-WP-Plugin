<?php
/**
 * Field partial: radio / checkbox group.
 *
 * @var array  $f             Field definition.
 * @var array  $options_array Parsed choice options.
 * @var string $error_id      Id of the group inline error element (aria-describedby).
 * @var bool   $is_required   Whether the field is required.
 */
?>
<?php
    $group_class = $f['type'] === 'radio' ? 'isxf-radio-group' : 'isxf-checkbox-group';
    $name_attr = $f['type'] === 'checkbox' ? esc_attr($f['name']) . '[]' : esc_attr($f['name']);
?>
                                    <div class="<?php echo $group_class; ?>">
                                        <?php foreach($options_array as $opt): if(empty($opt)) continue; ?>
                                            <label>
                                                <input type="<?php echo esc_attr($f['type']); ?>" name="<?php echo $name_attr; ?>" value="<?php echo esc_attr($opt); ?>"<?php if ( $is_required ) echo ' aria-required="true"'; ?> aria-describedby="<?php echo esc_attr($error_id); ?>">
                                                <?php echo esc_html($opt); ?>
                                            </label>
                                        <?php endforeach; ?>
                                        <?php if(empty($options_array)) echo '<span style="color:red; font-size:12px;">(' . esc_html__( 'No options have been configured by the admin yet', 'insightx-form' ) . ')</span>'; ?>
                                    </div>
