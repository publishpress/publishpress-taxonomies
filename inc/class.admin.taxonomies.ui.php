<?php

/**
 * Custom Taxonomy UI Admin UI
 *

 */
class taxopress_admin_ui
{
    /**
     * Return an opening `<fieldset>` tag.
     *
     * @param array $args Array of arguments.
     * @return string $value Opening `<fieldset>` tag.
     */
    public function get_fieldset_start($args = [])
    {
        $fieldset = '<fieldset';

        if (!empty($args['id'])) {
            $fieldset .= ' id="' . esc_attr($args['id']) . '"';
        }

        if (!empty($args['classes'])) {
            $classes  = 'class="' . implode(' ', $args['classes']) . '"';
            $fieldset .= ' ' . $classes;
        }

        if (!empty($args['aria-expanded'])) {
            $fieldset .= ' aria-expanded="' . $args['aria-expanded'] . '"';
        }

        $fieldset .= ' tabindex="0">';

        return $fieldset;
    }

    /**
     * Return an closing `<fieldset>` tag.
     *
     * @return string $value Closing `<fieldset>` tag.
     */
    public function get_fieldset_end()
    {
        return '</fieldset>';
    }

    /**
     * Return an opening `<legend>` tag.
     *
     * @return string
     */
    public function get_legend_start()
    {
        return '<legend class="screen-reader-text">';
    }

    /**
     * Return a closing `</legend>` tag.
     *
     * @return string
     */
    public function get_legend_end()
    {
        return '</legend>';
    }

    /**
     * Return string wrapped in a `<p>` tag.
     *
     * @param string $text Content to wrap in a `<p>` tag.
     * @return string $value Content wrapped in a `<p>` tag.
     */
    public function get_p($text = '')
    {
        return '<p>' . $text . '</p>';
    }

    /**
     * Return a populated `<select>` input.
     *
     * @param array $args Arguments to use with the `<select>` input.
     * @return string $value Complete <select> input with options and selected attribute.
     */
    public function get_select_checkbox_input($args = [])
    {
        $defaults = $this->get_default_input_parameters(
            [
                'class'    => '',
                'selections' => []
                ]
        );


        $args = wp_parse_args($args, $defaults);

        $value = '';
        if ($args['wrap']) {
            $value = $this->get_tr_start();
            $value .= $this->get_th_start();
            $value .= $this->get_label($args['name'], $args['labeltext']);
            if ($args['required']) {
                $value .= $this->get_required_span();
            }
            if (!empty($args['helptext'])) {
                $value .= $this->get_help($args['helptext']);
            }
            $value .= $this->get_th_end();
            $value .= $this->get_td_start();
        }

        $checkbox_html = '<input class="' . $args['class'] . '" type="checkbox" id="' . $args['name'] . '" name="' . $args['namearray'] . '[' . $args['name'] . ']" value="1" />';

        if (!empty($args['selections']['options']) && is_array($args['selections']['options'])) {
            foreach ($args['selections']['options'] as $val) {
                $result = '';
                $selected_result = false;
                $bool   = taxopress_disp_boolean($val['attr']);

                if (is_numeric($args['selections']['selected'])) {
                    $selected = taxopress_disp_boolean($args['selections']['selected']);
                } elseif (in_array($args['selections']['selected'], ['true', 'false'], true)) {
                    $selected = $args['selections']['selected'];
                }


                if (!empty($selected) && $selected === $bool) {
                    $result = ' selected="selected"';
                    $selected_result = true;
                } else {
                    if (array_key_exists('default', $val) && !empty($val['default'])) {
                        if (empty($selected)) {
                            $result = ' selected="selected"';
                            $selected_result = true;
                        }
                    }
                }

                if (!is_numeric($args['selections']['selected']) && (!empty($args['selections']['selected']) && $args['selections']['selected'] === $val['attr'])) {
                    $result = ' selected="selected"';
                    $selected_result = true;
                }

                if ($selected_result) {
                }

                if ($selected_result && (int)$val['attr'] === 1) {
                    $checkbox_html = '<input class="' . $args['class'] . '" type="checkbox" id="' . $args['name'] . '" name="' . $args['namearray'] . '[' . $args['name'] . ']" value="1" checked="checked" />';
                }
            }
        }
        $value .= $checkbox_html;

        if (!empty($args['aftertext'])) {
            $value .= ' ' . $args['aftertext'];
        }

        if ($args['wrap']) {
            $value .= $this->get_td_end();
            $value .= $this->get_tr_end();
        }

        return $value;
    }

    /**
     * Return a populated `radio` input.
     *
     * @param array $args Arguments to use with the `radio` input.
     * @return string $value Complete radio input with options and selected attribute.
     */
    public function get_radio_input($args = [])
    {
        $defaults = $this->get_default_input_parameters(
            [
                'class'    => '',
                'selections' => []
                ]
        );


        $args = wp_parse_args($args, $defaults);

        $value = '';
        if ($args['wrap']) {
            $value = $this->get_tr_start();
            $value .= $this->get_th_start();
            $value .= $this->get_label($args['name'], $args['labeltext']);
            if ($args['required']) {
                $value .= $this->get_required_span();
            }
            if (!empty($args['helptext'])) {
                $value .= $this->get_help($args['helptext']);
            }
            $value .= $this->get_th_end();
            $value .= $this->get_td_start();
        }

        $radio_html = '';

        if (!empty($args['selections']['options']) && is_array($args['selections']['options'])) {
            if (isset($args['selections']['selected']) && $args['selections']['selected'] !== '') {
                $selected_option = $args['selections']['selected'];
            } else {
                $selected_option = '';
            }
            foreach ($args['selections']['options'] as $val) {
                $checked = '';
                if (($selected_option == '' && !empty($val['default']) && $val['default'] == 'true') || ($selected_option !== '' && $selected_option == $val['attr'])) {
                    $checked = 'checked="checked"';
                }

                $radio_html .= '<div class="taxopress-radio-input"><label> <input class="' . $args['class'] . '" type="radio" id="' . $args['name'] . '-' . $val['attr'] . '" name="' . $args['namearray'] . '[' . $args['name'] . ']" value="' . $val['attr'] . '" ' . $checked . ' />' . $val['text'] . '</label></div>';
            }
        }
        $value .= $radio_html;

        if (!empty($args['aftertext'])) {
            $value .= '<p class="taxopress-field-description description">' . $args['aftertext'] . '</p>';
        }

        if ($args['wrap']) {
            $value .= $this->get_td_end();
            $value .= $this->get_tr_end();
        }

        return $value;
    }

    /**
     * Return a populated `<select>` input.
     *
     * @param array $args Arguments to use with the `<select>` input.
     * @return string $value Complete <select> input with options and selected attribute.
     */
    public function get_select_checkbox_input_main($args = [])
    {
        $defaults = $this->get_default_input_parameters(
            [
                'class'    => '',
                'selections' => [],
                'multiple' => false
            ]
        );

        $args = wp_parse_args($args, $defaults);

        $selectedresult = (isset($args['selections']) && isset($args['selections']['selected']) && !empty($args['selections']['selected'])) ? true : false;

        $multiple_select = ($args['multiple'] === true) ? '[]' : '';
        $multiple_text   = ($args['multiple'] === true) ? 'multiple' : '';

        $value = '';
        if ($args['wrap']) {
            $value = $this->get_tr_start();
            $value .= $this->get_th_start();
            $value .= $this->get_label($args['name'], $args['labeltext']);
            if ($args['required']) {
                $value .= $this->get_required_span();
            }
            if (!empty($args['helptext'])) {
                $value .= $this->get_help($args['helptext']);
            }
            $value .= $this->get_th_end();
            $value .= $this->get_td_start();
        }

        $value .= '<select class="' . $args['class'] . '" id="' . $args['name'] . '" name="' . $args['namearray'] . '[' . $args['name'] . ']' . $multiple_select . '" ' . $multiple_text . ' data-placeholder="' . sprintf(esc_html__('Select %1s...', 'simple-tags'), $args['labeltext']) . '">';
        if (!empty($args['selections']['options']) && is_array($args['selections']['options'])) {
            foreach ($args['selections']['options'] as $val) {
                $result = '';
                $bool   = taxopress_disp_boolean($val['attr']);
                $post_type_attr = isset($val['post_type']) ? 'data-post_type="' . $val['post_type'] . '"' : '';

                if (is_numeric($args['selections']['selected'])) {
                    $selected = taxopress_disp_boolean($args['selections']['selected']);
                } elseif (!is_array($args['selections']['selected']) && in_array($args['selections']['selected'], ['true', 'false'], true)) {
                    $selected = $args['selections']['selected'];
                }

                if (is_array($args['selections']['selected']) && in_array($val['attr'], $args['selections']['selected'])) {
                    $result = ' selected="selected"';
                } elseif (!empty($selected) && $selected === $bool) {
                    $result = ' selected="selected"';
                } else {
                    if (array_key_exists('default', $val) && !empty($val['default'])) {
                        if (empty($selected) && !$selectedresult) {
                            $result = ' selected="selected"';
                        }
                    }
                }

                if (!is_numeric($args['selections']['selected']) && (!empty($args['selections']['selected']) && $args['selections']['selected'] === $val['attr'])) {
                    $result = ' selected="selected"';
                }

                $value .= '<option ' . $post_type_attr . ' value="' . $val['attr'] . '"' . $result . '>' . $val['text'] . '</option>';
            }
        }
        $value .= '</select>';

        if (!empty($args['aftertext'])) {
            $value .= ' ' . $this->get_description($args['aftertext']);
        }

        if ($args['wrap']) {
            $value .= $this->get_td_end();
            $value .= $this->get_tr_end();
        }

        return $value;
    }

    /**
     * Return a populated `<select>` input.
     *
     * @param array $args Arguments to use with the `<select>` input.
     * @return string $value Complete <select> input with options and selected attribute.
     */
    public function get_select_number_select($args = [])
    {
        $defaults = $this->get_default_input_parameters(
            ['selections' => []]
        );

        $args = wp_parse_args($args, $defaults);

        $value = '';
        if ($args['wrap']) {
            $value = $this->get_tr_start();
            $value .= $this->get_th_start();
            $value .= $this->get_label($args['name'], $args['labeltext']);
            if ($args['required']) {
                $value .= $this->get_required_span();
            }
            if (!empty($args['helptext'])) {
                $value .= $this->get_help($args['helptext']);
            }
            $value .= $this->get_th_end();
            $value .= $this->get_td_start();
        }

        $value .= '<select id="' . $args['name'] . '" name="' . $args['namearray'] . '[' . $args['name'] . ']">';
        if (!empty($args['selections']['options']) && is_array($args['selections']['options'])) {
            foreach ($args['selections']['options'] as $val) {
                $result = '';
                $bool   = ($val['attr']);

                if (is_numeric($args['selections']['selected'])) {
                    $selected = ($args['selections']['selected']);
                } elseif (in_array($args['selections']['selected'], ['true', 'false'], true)) {
                    $selected = $args['selections']['selected'];
                }

                if (!empty($selected) && $selected === $bool) {
                    $result = ' selected="selected"';
                } else {
                    if (array_key_exists('default', $val) && !empty($val['default'])) {
                        if (empty($selected)) {
                            $result = ' selected="selected"';
                        }
                    }
                }

                if (!is_numeric($args['selections']['selected']) && (!empty($args['selections']['selected']) && $args['selections']['selected'] === $val['attr'])) {
                    $result = ' selected="selected"';
                }

                $value .= '<option value="' . $val['attr'] . '"' . $result . '>' . $val['text'] . '</option>';
            }
        }
        $value .= '</select>';

        if (!empty($args['aftertext'])) {
            $value .= ' ' . $this->get_description($args['aftertext']);
        }

        if ($args['wrap']) {
            $value .= $this->get_td_end();
            $value .= $this->get_tr_end();
        }

        return $value;
    }

    /**
     * Return some array_merged default arguments for all input types.
     *
     * @param array $additions Arguments array to merge with our defaults.
     * @return array $value Merged arrays for our default parameters.
     */
    public function get_default_input_parameters($additions = [])
    {
        return array_merge(
            [
                'namearray'      => '',
                'name'           => '',
                'textvalue'      => '',
                'labeltext'      => '',
                'aftertext'      => '',
                'helptext'       => '',
                'helptext_after' => false,
                'required'       => false,
                'wrap'           => true,
                'placeholder'    => true,
            ],
            (array)$additions
        );
    }

    /**
     * Return an opening `<tr>` tag.
     *
     * @return string $value Opening `<tr>` tag with attributes.
     */
    public function get_tr_start()
    {
        return '<tr valign="top">';
    }

    /**
     * Return an opening `<th>` tag.
     *
     * @return string $value Opening `<th>` tag with attributes.
     */
    public function get_th_start()
    {
        return '<th scope="row">';
    }

    /**
     * Return a form <label> with for attribute.
     *
     * @param string $label_for Form input to associate `<label>` with.
     * @param string $label_text Text to display in the `<label>` tag.
     * @return string $value `<label>` tag with filled out parts.
     */
    public function get_label($label_for = '', $label_text = '', $labeldescription = false)
    {
        if ($labeldescription === 2) {
            return '<label for="' . esc_attr($label_for) . '"><code>' . htmlentities('[' . $label_text . ']') . '</code></label>';
        } elseif ($labeldescription === 3) {
            return '<label for="' . esc_attr($label_for) . '"><code>' . htmlentities($label_text) . '</code></label>';
        } elseif ($labeldescription) {
            return '<label for="' . esc_attr($label_for) . '"><code>' . htmlentities('<' . $label_text . '> </' . $label_text . '>') . '</code></label>';
        } else {
            return '<label for="' . esc_attr($label_for) . '">' . wp_strip_all_tags($label_text) . '</label>';
        }
    }

    /**
     * Return a `<span>` to indicate required status, with class attribute.
     *
     * @return string Span tag.
     */
    public function get_required_span()
    {
        return ' <span class="required">*</span>';
    }

    /**
     * Return an `<a>` tag with title attribute holding help text.
     *
     * @param string $help_text Text to use in the title attribute.
     * @return string <a> tag with filled out parts.
     */
    public function get_help($help_text = '')
    {
        return '<span class="taxopress-help-tooltip dashicons-before dashicons-editor-help"><span class="tooltip-text">' . esc_html($help_text) . '</span></span>';
    }

    /**
     * Return a closing `</th>` tag.
     *
     * @return string $value Closing `</th>` tag.
     */
    public function get_th_end()
    {
        return '</th>';
    }

    /**
     * Return an opening `<td>` tag.
     *
     * @return string $value Opening `<td>` tag.
     */
    public function get_td_start()
    {
        return '<td>';
    }

    /**
     * Return a `<span>` tag with the help text.
     *
     * @param string $help_text Text to display after the input.
     * @return string
     */
    public function get_description($help_text = '')
    {
        return '<p class="taxopress-field-description description">' . $help_text . '</p>';
    }

    /**
     * Return a closing `</td>` tag.
     *
     * @return string $value Closing `</td>` tag.
     */
    public function get_td_end()
    {
        return '</td>';
    }

    /**
     * Return a closing `</tr>` tag.
     *
     * @return string $value Closing `</tr>` tag.
     */
    public function get_tr_end()
    {
        return '</tr>';
    }

    /**
     * Return a text input element.
     *
     * @param array $args Arguments to use with the text input.
     * @return string Complete text input element with proper attributes.
     */
    public function get_text_input($args = [])
    {
        $defaults = $this->get_default_input_parameters(
            [
                'maxlength' => '',
                'onblur'    => '',
                'class'     => '',
            ]
        );
        $args     = wp_parse_args($args, $defaults);

        $value = '';
        if ($args['wrap']) {
            $value .= $this->get_tr_start();
            $value .= $this->get_th_start();
            $value .= $this->get_label($args['name'], $args['labeltext']);
            if ($args['required']) {
                $value .= $this->get_required_span();
            }
            $value .= $this->get_th_end();
            $value .= $this->get_td_start();
        }

        if (isset($args['toplabel'])) {
            $value .= $this->get_label($args['name'], $args['toplabel']) . '<br />';
        }

        $value .= '<input type="text" id="' . $args['name'] . '" class="' . $args['class'] . '" name="' . $args['namearray'] . '[' . $args['name'] . ']" value="' . $args['textvalue'] . '"';

        if ($args['maxlength']) {
            $value .= ' ' . $this->get_maxlength($args['maxlength']);
        }

        if ($args['onblur']) {
            $value .= ' ' . $this->get_onblur($args['onblur']);
        }

        $value .= ' ' . $this->get_aria_required($args['required']);

        $value .= ' ' . $this->get_required_attribute($args['required']);

        if (!empty($args['aftertext'])) {
            if ($args['placeholder']) {
                $value .= ' ' . $this->get_placeholder($args['aftertext']);
            }
        }

        if (!empty($args['data'])) {
            foreach ($args['data'] as $dkey => $dvalue) {
                $value .= " data-{$dkey}=\"{$dvalue}\"";
            }
        }

        $value .= ' />';

        if (!empty($args['aftertext'])) {
            $value .= $this->get_hidden_text($args['aftertext']);
        }

        if ($args['helptext']) {
            $value .= '<br/>' . $this->get_description($args['helptext']);
        }

        if ($args['wrap']) {
            $value .= $this->get_td_end();
            $value .= $this->get_tr_end();
        }

        return $value;
    }

    /**
     * Return a number input.
     *
     * @param array $args Arguments to use with the text input.
     * @return string Complete number input element with proper attributes.
     */
    public function get_number_input($args = [])
    {
        $defaults = $this->get_default_input_parameters(
            [
                'maxlength' => '',
                'onblur'    => '',
                'min'       => '1',
                'max'       => '1000000000',
                'other_attr' => '',
                'class'     => '',
            ]
        );
        $args     = wp_parse_args($args, $defaults);

        $value = '';
        if ($args['wrap']) {
            $value .= $this->get_tr_start();
            $value .= $this->get_th_start();
            $value .= $this->get_label($args['name'], $args['labeltext']);
            if ($args['required']) {
                $value .= $this->get_required_span();
            }
            $value .= $this->get_th_end();
            $value .= $this->get_td_start();
        }

        if (isset($args['toplabel'])) {
            $value .= $this->get_label($args['name'], $args['toplabel']) . '<br />';
        }

        $value .= '<input min="' . $args['min'] . '" max="' . $args['max'] . '" type="number" id="' . $args['name'] . '"  class="' . $args['class'] . '" name="' . $args['namearray'] . '[' . $args['name'] . ']" value="' . $args['textvalue'] . '" ' . $args['other_attr'] . '';

        if ($args['maxlength']) {
            $value .= ' ' . $this->get_maxlength($args['maxlength']);
        }

        if ($args['onblur']) {
            $value .= ' ' . $this->get_onblur($args['onblur']);
        }

        $value .= ' ' . $this->get_aria_required($args['required']);

        $value .= ' ' . $this->get_required_attribute($args['required']);

        if (!empty($args['aftertext'])) {
            if ($args['placeholder']) {
                $value .= ' ' . $this->get_placeholder($args['aftertext']);
            }
        }

        if (!empty($args['data'])) {
            foreach ($args['data'] as $dkey => $dvalue) {
                $value .= " data-{$dkey}=\"{$dvalue}\"";
            }
        }

        $value .= ' />';

        if (!empty($args['aftertext'])) {
            $value .= $this->get_hidden_text($args['aftertext']);
        }

        if ($args['helptext']) {
            $value .= '<br/>' . $this->get_description($args['helptext']);
        }

        if ($args['wrap']) {
            $value .= $this->get_td_end();
            $value .= $this->get_tr_end();
        }

        return $value;
    }

    /**
     * Return a maxlength HTML attribute with a specified length.
     *
     * @param string $length How many characters the max length should be set to.
     * @return string $value Maxlength HTML attribute.
     */
    public function get_maxlength($length = '')
    {
        return 'maxlength="' . esc_attr($length) . '"';
    }

    /**
     * Return a onblur HTML attribute for a specified value.
     *
     * @param string $text Text to place in the onblur attribute.
     * @return string $value Onblur HTML attribute.
     */
    public function get_onblur($text = '')
    {
        return 'onblur="' . esc_attr($text) . '"';
    }

    /**
     * Return an aria-required attribute set to true.
     *
     * @param bool $required Whether or not the field is required.
     * @return string Aria required attribute
     */
    public function get_aria_required($required = false)
    {
        $attr = $required ? 'true' : 'false';

        return 'aria-required="' . $attr . '"';
    }

    /**
     * Return an html attribute denoting a required field.
     *
     * @param bool $required Whether or not the field is required.
     * @return string `Required` attribute.
     */
    public function get_required_attribute($required = false)
    {
        $attr = '';
        if ($required) {
            $attr .= 'required="true"';
        }

        return $attr;
    }

    /**
     * Return a placeholder HTML attribtue for a specified value.
     *
     * @param string $text Text to place in the placeholder attribute.
     * @return string $value Placeholder HTML attribute.
     */
    public function get_placeholder($text = '')
    {
        return 'placeholder="' . esc_attr($text) . '"';
    }

    /**
     * Return a span that will only be visible for screenreaders.
     *
     * @param string $text Text to visually hide.
     * @return string $value Visually hidden text meant for screen readers.
     */
    public function get_hidden_text($text = '')
    {
        return '<span class="visuallyhidden">' . $text . '</span>';
    }

    /**
     * Return a `<textarea>` input.
     *
     * @param array $args Arguments to use with the textarea input.
     * @return string $value Complete <textarea> input with proper attributes.
     */
    public function get_textarea_input($args = [])
    {
        $defaults = $this->get_default_input_parameters(
            [
                'rows' => '',
                'cols' => '',
                'class'     => '',
            ]
        );
        $args     = wp_parse_args($args, $defaults);

        $value = '';

        if ($args['wrap']) {
            $value .= $this->get_tr_start();
            $value .= $this->get_th_start();
            $value .= $this->get_label($args['name'], $args['labeltext']);
            if ($args['required']) {
                $value .= $this->get_required_span();
            }
            $value .= $this->get_th_end();
            $value .= $this->get_td_start();
        }

        $value .= '<textarea id="' . $args['name'] . '" name="' . $args['namearray'] . '[' . $args['name'] . ']" class="' . $args['class'] . '" rows="' . $args['rows'] . '" cols="' . $args['cols'] . '">' . $args['textvalue'] . '</textarea>';

        if (!empty($args['aftertext'])) {
            $value .= $args['aftertext'];
        }

        if ($args['helptext']) {
            $value .= '<br/>' . $this->get_description($args['helptext']);
        }

        if ($args['wrap']) {
            $value .= $this->get_td_end();
            $value .= $this->get_tr_end();
        }

        return $value;
    }

    /**
     * Return a checkbox input element.
     *
     * @param array $args Arguments to use with the checkbox input.
     * @return string $value Complete checkbox input element with proper attributes.
     */
    public function get_check_input($args = [])
    {
        $defaults = $this->get_default_input_parameters(
            [
                'checkvalue'    => '',
                'checked'       => 'true',
                'checklisttext' => '',
                'labeldescription' => false,
                'default'       => false,
                'add_delete'       => false,
            ]
        );
        $args     = wp_parse_args($args, $defaults);

        $value = '';
        if ($args['wrap']) {
            $value .= $this->get_tr_start();
            $value .= $this->get_th_start();
            $value .= $args['checklisttext'];
            if ($args['required']) {
                $value .= $this->get_required_span();
            }
            $value .= $this->get_th_end();
            $value .= $this->get_td_start();
        }

        if (isset($args['checked']) && 'false' === $args['checked']) {
            $value .= '<input type="checkbox" id="' . $args['name'] . '" name="' . $args['namearray'] . '[]" value="' . $args['checkvalue'] . '" />';
        } else {
            $value .= '<input type="checkbox" id="' . $args['name'] . '" name="' . $args['namearray'] . '[]" value="' . $args['checkvalue'] . '" checked="checked" />';
        }
        $value .= $this->get_label($args['name'], $args['labeltext'], $args['labeldescription']);

        if ($args['add_delete']) {
            $value .= '<span class="delete">' . esc_html__('Delete', 'simple-tags') . '</span>';
        }

        $value .= '<br/>';

        if ($args['wrap']) {
            $value .= $this->get_td_end();
            $value .= $this->get_tr_end();
        }

        return $value;
    }

    /**
     * Return a button input element.
     *
     * @param array $args Arguments to use with the button input.
     * @return string Complete button input element.
     */
    public function get_button($args = [])
    {
        $value = '';
        $value .= '<input id="' . $args['id'] . '" class="button" type="button" value="' . $args['textvalue'] . '" />';

        return $value;
    }

    /**
     * Returns an HTML block for previewing the menu icon.
     *
     * @param string $menu_icon URL or a name of the dashicons class.
     *
     * @return string $value HTML block with a layout of the menu icon preview.
     */
    public function get_menu_icon_preview($menu_icon = '')
    {
        $content = '';
        if (!empty($menu_icon)) {
            $content = '<img src="' . $menu_icon . '">';
            if (0 === strpos($menu_icon, 'dashicons-')) {
                $content = '<div class="dashicons-before ' . $menu_icon . '"></div>';
            }
        }

        return '<div id="menu_icon_preview">' . $content . '</div>';
    }
}
