<?php
/**
 * FormBuilder — dependency-free HTML form builder with validation.
 *
 * Build a form fluently, render it, then validate the submission with the
 * same definition. Handles labels, error messages, value repopulation,
 * CSRF tokens and a honeypot field. No framework, no JavaScript.
 *
 *     $form = new Azanumer\FormBuilder( '/contact.php' );
 *     $form->add( 'name', 'text', array( 'label' => 'Your name', 'required' => true ) );
 *     $form->add( 'email', 'email', array( 'label' => 'Email', 'required' => true ) );
 *     $form->add( 'message', 'textarea', array( 'label' => 'Message', 'required' => true, 'min_length' => 10 ) );
 *     $form->add( 'topic', 'select', array( 'label' => 'Topic', 'options' => array( 'general' => 'General', 'support' => 'Support' ) ) );
 *
 *     if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
 *         if ( $form->validate( $_POST ) ) {
 *             $data = $form->values(); // sanitized values
 *         }
 *     }
 *     echo $form->render();
 *
 * Field options: label, required, placeholder, value, options (select),
 * min_length, max_length, pattern (PCRE), validate (callable returning an
 * error string or null). CSRF is on by default when a session is active;
 * call ->csrf( false ) to disable. The honeypot field "website" is on by
 * default — bots fill it, humans never see it.
 */

namespace Azanumer;

class FormBuilder {

    /** @var string Form action URL. */
    private $action;

    /** @var string HTTP method. */
    private $method;

    /** @var array Field definitions, keyed by field name. */
    private $fields = array();

    /** @var array Validation errors, keyed by field name. */
    private $errors = array();

    /** @var array Sanitized submitted values. */
    private $values = array();

    /** @var bool Whether CSRF protection is enabled. */
    private $csrf = true;

    /** @var string|null Honeypot field name, null to disable. */
    private $honeypot = 'website';

    /** @var string Submit button label. */
    private $submitLabel = 'Submit';

    public function __construct( $action = '', $method = 'post' ) {
        $this->action = $action;
        $this->method = strtolower( $method ) === 'get' ? 'get' : 'post';
    }

    /**
     * Add a field. Types: text, email, password, textarea, select,
     * checkbox, hidden.
     */
    public function add( $name, $type, array $options = array() ) {
        $defaults = array(
            'label'      => ucfirst( str_replace( '_', ' ', $name ) ),
            'required'   => false,
            'placeholder' => '',
            'value'      => '',
            'options'    => array(),
            'min_length' => 0,
            'max_length' => 0,
            'pattern'    => '',
            'validate'   => null,
        );
        $this->fields[ $name ] = array_merge( $defaults, $options );
        $this->fields[ $name ]['type'] = $type;
        return $this;
    }

    public function csrf( $enabled ) {
        $this->csrf = (bool) $enabled;
        return $this;
    }

    public function honeypot( $fieldName ) {
        $this->honeypot = ( null === $fieldName ) ? null : (string) $fieldName;
        return $this;
    }

    public function submitLabel( $label ) {
        $this->submitLabel = (string) $label;
        return $this;
    }

    public function errors() {
        return $this->errors;
    }

    public function values() {
        return $this->values;
    }

    public function hasErrors() {
        return ! empty( $this->errors );
    }

    /* ---------------- CSRF ---------------- */

    private function csrfToken() {
        if ( session_status() !== PHP_SESSION_ACTIVE ) {
            return '';
        }
        if ( empty( $_SESSION['form_builder_csrf'] ) ) {
            $_SESSION['form_builder_csrf'] = bin2hex( random_bytes( 32 ) );
        }
        return $_SESSION['form_builder_csrf'];
    }

    /* ---------------- Rendering ---------------- */

    public function render() {
        $html = '<form action="' . $this->esc( $this->action ) . '" method="' . $this->method . '" novalidate>' . "\n";

        if ( $this->csrf ) {
            $token = $this->csrfToken();
            if ( '' !== $token ) {
                $html .= '<input type="hidden" name="_csrf" value="' . $this->esc( $token ) . '">' . "\n";
            }
        }

        if ( null !== $this->honeypot ) {
            $html .= '<div style="display:none" aria-hidden="true">'
                . '<label>' . $this->esc( $this->honeypot )
                . '<input type="text" name="' . $this->esc( $this->honeypot ) . '" value="" tabindex="-1" autocomplete="off">'
                . '</label></div>' . "\n";
        }

        foreach ( $this->fields as $name => $field ) {
            $html .= $this->renderField( $name, $field );
        }

        $html .= '<button type="submit">' . $this->esc( $this->submitLabel ) . '</button>' . "\n";
        $html .= '</form>';
        return $html;
    }

    private function renderField( $name, $field ) {
        $value   = isset( $this->values[ $name ] ) ? $this->values[ $name ] : $field['value'];
        $error   = isset( $this->errors[ $name ] ) ? $this->errors[ $name ] : '';
        $reqAttr = $field['required'] ? ' required' : '';
        $id      = 'fb_' . $name;

        $html = '<div class="fb-field">' . "\n";
        if ( 'hidden' !== $field['type'] && 'checkbox' !== $field['type'] ) {
            $html .= '<label for="' . $this->esc( $id ) . '">' . $this->esc( $field['label'] )
                . ( $field['required'] ? ' *' : '' ) . '</label>' . "\n";
        }

        $attrs = ' id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '"' . $reqAttr;
        if ( '' !== $field['placeholder'] ) {
            $attrs .= ' placeholder="' . $this->esc( $field['placeholder'] ) . '"';
        }

        switch ( $field['type'] ) {
            case 'textarea':
                $html .= '<textarea' . $attrs . '>' . $this->esc( (string) $value ) . '</textarea>' . "\n";
                break;
            case 'select':
                $html .= '<select' . $attrs . '>' . "\n";
                foreach ( $field['options'] as $optValue => $optLabel ) {
                    $selected = ( (string) $value === (string) $optValue ) ? ' selected' : '';
                    $html .= '<option value="' . $this->esc( (string) $optValue ) . '"' . $selected . '>'
                        . $this->esc( (string) $optLabel ) . '</option>' . "\n";
                }
                $html .= '</select>' . "\n";
                break;
            case 'checkbox':
                $checked = $value ? ' checked' : '';
                $html .= '<label for="' . $this->esc( $id ) . '"><input type="checkbox"' . $attrs . ' value="1"' . $checked . '> '
                    . $this->esc( $field['label'] ) . ( $field['required'] ? ' *' : '' ) . '</label>' . "\n";
                break;
            case 'hidden':
                $html .= '<input type="hidden" name="' . $this->esc( $name ) . '" value="' . $this->esc( (string) $value ) . '">' . "\n";
                break;
            default: // text, email, password, and anything else sane
                $type = in_array( $field['type'], array( 'text', 'email', 'password' ), true ) ? $field['type'] : 'text';
                $html .= '<input type="' . $type . '"' . $attrs . ' value="' . $this->esc( (string) $value ) . '">' . "\n";
                break;
        }

        if ( '' !== $error ) {
            $html .= '<span class="fb-error">' . $this->esc( $error ) . '</span>' . "\n";
        }
        $html .= '</div>' . "\n";
        return $html;
    }

    /* ---------------- Validation ---------------- */

    /**
     * Validate submitted data against the field definitions.
     * Returns true when everything passes; errors() / values() hold the rest.
     */
    public function validate( array $data ) {
        $this->errors = array();
        $this->values = array();

        // Honeypot: any value means a bot filled the hidden field.
        if ( null !== $this->honeypot && ! empty( $data[ $this->honeypot ] ) ) {
            $this->errors['_spam'] = 'Spam detected.';
            return false;
        }

        // CSRF: only enforced when a session is active and a token exists.
        if ( $this->csrf && session_status() === PHP_SESSION_ACTIVE && ! empty( $_SESSION['form_builder_csrf'] ) ) {
            $submitted = isset( $data['_csrf'] ) ? (string) $data['_csrf'] : '';
            if ( ! hash_equals( $_SESSION['form_builder_csrf'], $submitted ) ) {
                $this->errors['_csrf'] = 'Security token expired — please submit the form again.';
                return false;
            }
        }

        foreach ( $this->fields as $name => $field ) {
            $raw = isset( $data[ $name ] ) ? $data[ $name ] : '';
            if ( 'checkbox' === $field['type'] ) {
                $this->values[ $name ] = ! empty( $raw ) ? true : false;
                if ( $field['required'] && empty( $raw ) ) {
                    $this->errors[ $name ] = $field['label'] . ' is required.';
                }
                continue;
            }

            $value = is_string( $raw ) ? trim( $raw ) : $raw;
            $this->values[ $name ] = $value;

            if ( $field['required'] && '' === $value ) {
                $this->errors[ $name ] = $field['label'] . ' is required.';
                continue;
            }
            if ( '' === $value ) {
                continue; // Optional and empty — nothing more to check.
            }

            if ( 'email' === $field['type'] && false === filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
                $this->errors[ $name ] = 'Please enter a valid email address.';
                continue;
            }
            if ( $field['min_length'] > 0 && mb_strlen( $value ) < $field['min_length'] ) {
                $this->errors[ $name ] = $field['label'] . ' must be at least ' . $field['min_length'] . ' characters.';
                continue;
            }
            if ( $field['max_length'] > 0 && mb_strlen( $value ) > $field['max_length'] ) {
                $this->errors[ $name ] = $field['label'] . ' must be at most ' . $field['max_length'] . ' characters.';
                continue;
            }
            if ( '' !== $field['pattern'] && 1 !== preg_match( $field['pattern'], $value ) ) {
                $this->errors[ $name ] = $field['label'] . ' is not in the expected format.';
                continue;
            }
            if ( is_callable( $field['validate'] ) ) {
                $customError = call_user_func( $field['validate'], $value, $data );
                if ( is_string( $customError ) && '' !== $customError ) {
                    $this->errors[ $name ] = $customError;
                }
            }
        }

        return empty( $this->errors );
    }

    private function esc( $value ) {
        return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
    }
}
