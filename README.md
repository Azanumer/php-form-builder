# PHP Form Builder

A dependency-free HTML form builder with validation — define the fields once, get rendering, error messages, value repopulation, CSRF protection and a honeypot for free.

## Usage

```php
require_once 'src/FormBuilder.php';

use Azanumer\FormBuilder;

session_start();

$form = new FormBuilder( '/contact.php' );
$form->add( 'name', 'text', array( 'label' => 'Your name', 'required' => true ) );
$form->add( 'email', 'email', array( 'label' => 'Email', 'required' => true ) );
$form->add( 'message', 'textarea', array( 'label' => 'Message', 'required' => true, 'min_length' => 10 ) );
$form->add( 'topic', 'select', array( 'label' => 'Topic', 'options' => array( 'general' => 'General', 'support' => 'Support' ) ) );

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    if ( $form->validate( $_POST ) ) {
        $data = $form->values(); // sanitized values — mail them, save them, done
    }
}

echo $form->render();
```

## Field types

`text`, `email`, `password`, `textarea`, `select`, `checkbox`, `hidden`.

## Field options

| Option | What it does |
| ------ | ------------ |
| `label` | Label text (defaults to the field name, prettified) |
| `required` | Field must not be empty |
| `placeholder` | Placeholder text |
| `value` | Default value |
| `options` | `value => label` pairs for `select` |
| `min_length` / `max_length` | Character length limits |
| `pattern` | PCRE the value must match |
| `validate` | Custom callable `fn($value, $data)` returning an error string or `null` |

## Built-in protection

- **CSRF token** — session token in a hidden field, verified with `hash_equals()`; disable with `->csrf(false)`.
- **Honeypot** — hidden `website` field bots fill and humans never see; disable with `->honeypot(null)`.

## Demo

```bash
php -S localhost:8080 examples/form-example.php
```

## License

MIT
