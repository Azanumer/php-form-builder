<?php
/**
 * Contact-form demo for php-form-builder.
 *
 * Run with the built-in server:
 *   php -S localhost:8080 examples/form-example.php
 */

require_once __DIR__ . '/../src/FormBuilder.php';

use Azanumer\FormBuilder;

session_start();

$form = new FormBuilder( '' );
$form->add( 'name', 'text', array(
    'label'      => 'Your name',
    'required'   => true,
    'min_length' => 2,
    'max_length' => 80,
    'placeholder' => 'Jane Doe',
) );
$form->add( 'email', 'email', array(
    'label'    => 'Email',
    'required' => true,
    'placeholder' => 'jane@example.com',
) );
$form->add( 'topic', 'select', array(
    'label'   => 'Topic',
    'options' => array(
        'general' => 'General question',
        'support' => 'Support',
        'sales'   => 'Sales',
    ),
) );
$form->add( 'message', 'textarea', array(
    'label'      => 'Message',
    'required'   => true,
    'min_length' => 10,
    'validate'   => function ( $value ) {
        // Custom rule: block messages that are only a URL.
        if ( 1 === preg_match( '#^https?://\S+$#', $value ) ) {
            return 'Please write a message, not just a link.';
        }
        return null;
    },
) );
$form->add( 'newsletter', 'checkbox', array(
    'label' => 'Subscribe to the newsletter',
) );
$form->submitLabel( 'Send message' );

$success = false;
if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    if ( $form->validate( $_POST ) ) {
        $success = true;
        // In a real app: mail() / save to DB here using $form->values().
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>FormBuilder demo</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 560px; margin: 2rem auto; padding: 0 1rem; }
  .fb-field { margin-bottom: 1rem; }
  .fb-field label { display: block; margin-bottom: .25rem; font-weight: 600; }
  .fb-field input, .fb-field textarea, .fb-field select { width: 100%; padding: .5rem; box-sizing: border-box; }
  .fb-error { color: #b91c1c; font-size: .875rem; }
  .ok { background: #dcfce7; padding: 1rem; border-radius: .5rem; }
</style>
</head>
<body>
<h1>Contact us</h1>
<?php if ( $success ) : ?>
  <p class="ok">Thank you, <?php echo htmlspecialchars( $form->values()['name'], ENT_QUOTES, 'UTF-8' ); ?> — your message was received.</p>
<?php else : ?>
  <?php echo $form->render(); ?>
<?php endif; ?>
</body>
</html>
