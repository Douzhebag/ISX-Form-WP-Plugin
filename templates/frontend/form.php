<?php
/**
 * Template: dynamic form markup for the [advanced_form] / [isxf_form]
 * shortcodes.
 *
 * Extracted from ISXF\Frontend::render_dynamic_form() (Phase 2.2 view
 * split). Data gathering stays in the render method; this file receives:
 *
 * @var int    $form_id            Form post ID.
 * @var array  $fields             Field definitions.
 * @var string $service            Active captcha service ('google'|'cloudflare').
 * @var string $turnstile_site_key Turnstile site key ('' when unset).
 * @var string $button_style       Inline CSS vars for custom button style ('' = default).
 * @var string $button_css         Sanitized advanced custom CSS ('' = none), scoped below.
 *
 * Each field type renders through a partial in templates/frontend/fields/.
 *
 * Accessibility (Phase 2.6): every input gets a unique id
 * (isxf-field-{form_id}-{name}) with a matching label[for]; radio/checkbox
 * groups use fieldset/legend; required fields carry aria-required; each
 * field has a linked (aria-describedby) inline error element
 * (isxf-error-{form_id}-{name}) that the frontend JS fills on validation
 * failure. Field names are sanitize_key()'d on save, so they are safe to
 * embed in ids. Native browser validation is disabled (novalidate) — the
 * JS gates the submit button and shows inline errors instead, so no
 * `required` attribute is emitted (aria-required only).
 */
?>
        <div class="isxf-form-container isxf-form-<?php echo esc_attr( $form_id ); ?>"<?php if ( ! empty( $button_style ) ) echo ' style="' . esc_attr( $button_style ) . '"'; ?>>
            <form class="advanced-contact-form" method="POST" novalidate>
                <?php wp_nonce_field( 'isxf_secure_nonce', 'isxf_nonce' ); ?>
                <input type="hidden" name="action" value="isxf_submit_form">
                <input type="hidden" name="isxf_form_id" value="<?php echo esc_attr($form_id); ?>">
                <input type="text" name="isxf_website_url_trap" value="" style="display:none !important; visibility:hidden;" tabindex="-1" autocomplete="off">
                
                <div class="isxf-form-grid">
                    <?php foreach($fields as $f):
                        if ( !isset($f['type'], $f['name'], $f['label']) ) continue;

                        $width_class = (isset($f['width']) && $f['width'] === '50') ? 'isxf-col-50' : 'isxf-col-100';
                        $options_array = !empty($f['options']) ? array_map('trim', explode(',', $f['options'])) : [];

                        $input_class = '';
                        if ( $f['type'] === 'date' ) {
                            $input_class = 'isxf-modern-date';
                        } elseif ( $f['type'] === 'check_in' ) {
                            $input_class = 'isxf-date-check-in';
                        } elseif ( $f['type'] === 'check_out' ) {
                            $input_class = 'isxf-date-check-out';
                        }

                        $is_choice = ( $f['type'] === 'radio' || $f['type'] === 'checkbox' );
                        $is_required = ( $f['required'] === 'yes' );
                        $field_id = 'isxf-field-' . $form_id . '-' . $f['name'];
                        $error_id = 'isxf-error-' . $form_id . '-' . $f['name'];
                    ?>
                        <div class="isxf-field-wrapper <?php echo esc_attr($width_class); ?>" data-type="<?php echo esc_attr($f['type']); ?>" data-required="<?php echo esc_attr($f['required']); ?>">
                            
                            <?php if ( $f['type'] === 'heading' ) : ?>
                                <?php \ISXF\Template::render( 'frontend/fields/heading', [ 'f' => $f ] ); ?>
                            <?php elseif ( $is_choice ) : ?>
                                <fieldset class="isxf-choice-fieldset">
                                    <legend class="isxf-field-label isxf-choice-legend">
                                        <?php echo esc_html($f['label']); ?>
                                        <?php if ( $is_required ) echo '<span class="isxf-req-mark">*</span>'; ?>
                                    </legend>
                                    <?php \ISXF\Template::render( 'frontend/fields/choice-group', [ 'f' => $f, 'options_array' => $options_array, 'is_required' => $is_required, 'error_id' => $error_id ] ); ?>
                                    <p class="isxf-field-error" id="<?php echo esc_attr($error_id); ?>" role="alert" hidden></p>
                                </fieldset>
                            <?php else : ?>
                                <label class="isxf-field-label" for="<?php echo esc_attr($field_id); ?>">
                                    <?php echo esc_html($f['label']); ?>
                                    <?php if ( $is_required ) echo '<span class="isxf-req-mark">*</span>'; ?>
                                </label>
                                
                                <?php if ( $f['type'] === 'textarea' ) : ?>
                                    <?php \ISXF\Template::render( 'frontend/fields/textarea', [ 'f' => $f, 'field_id' => $field_id, 'error_id' => $error_id, 'is_required' => $is_required ] ); ?>
                                
                                <?php elseif ( $f['type'] === 'select' ) : ?>
                                    <?php \ISXF\Template::render( 'frontend/fields/select', [ 'f' => $f, 'options_array' => $options_array, 'field_id' => $field_id, 'error_id' => $error_id, 'is_required' => $is_required ] ); ?>

                                <?php else : ?>
                                    <?php \ISXF\Template::render( 'frontend/fields/input', [ 'f' => $f, 'input_class' => $input_class, 'field_id' => $field_id, 'error_id' => $error_id, 'is_required' => $is_required ] ); ?>
                                <?php endif; ?>

                                <p class="isxf-field-error" id="<?php echo esc_attr($error_id); ?>" role="alert" hidden></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if($service === 'cloudflare' && $turnstile_site_key): ?>
                    <div class="cf-turnstile" style="margin-top: 15px;" data-sitekey="<?php echo esc_attr($turnstile_site_key); ?>"></div>
                <?php endif; ?>
                
                <p class="isxf-submit-hint" id="isxf-submit-hint-<?php echo esc_attr($form_id); ?>"><?php esc_html_e( 'Please fill in all required fields to submit the form.', 'insightx-form' ); ?></p>
                <button type="submit" class="isxf-submit-btn" aria-label="<?php esc_attr_e( 'Submit Form', 'insightx-form' ); ?>" aria-busy="false" aria-describedby="isxf-submit-hint-<?php echo esc_attr($form_id); ?>"><?php esc_html_e( 'Submit Form', 'insightx-form' ); ?></button>
            </form>
        </div>
        <?php if ( ! empty( $button_css ) ) : ?>
            <style>
                .isxf-form-container.isxf-form-<?php echo esc_attr( $form_id ); ?> .isxf-submit-btn { <?php echo $button_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized in Frontend::sanitize_custom_css() ?> }
            </style>
        <?php endif; ?>
        