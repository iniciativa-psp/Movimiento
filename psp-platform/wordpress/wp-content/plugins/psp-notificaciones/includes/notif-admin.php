<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu','psp_notif_admin_menu');
function psp_notif_admin_menu() {
    add_submenu_page('psp-core','Notificaciones','&#x1F514; Notificaciones','manage_options','psp-notificaciones','psp_notif_admin_page');
}

function psp_notif_admin_page() {
    if (isset($_POST['psp_save_notif']) && check_admin_referer('psp_notif')) {
        $fields = ['psp_twilio_sid','psp_twilio_token','psp_twilio_from','psp_wa_360_token','psp_wa_360_url','psp_notif_email_from'];
        foreach ($fields as $f) update_option($f, sanitize_text_field($_POST[str_replace('psp_','',$f)]??''));
        update_option('psp_notif_activo', isset($_POST['notif_activo'])?'1':'0');
        echo '<div class="updated"><p>&#x2705; Guardado.</p></div>';
    }
    ?>
    <div class="wrap">
      <h1>&#x1F514; Configuraci&oacute;n de Notificaciones</h1>
      <form method="post">
        <?php wp_nonce_field('psp_notif'); ?>
        <table class="form-table">
          <tr><th>Activar notificaciones</th>
              <td><label><input type="checkbox" name="notif_activo" <?php checked(get_option('psp_notif_activo','1')); ?>> Activo</label></td></tr>
          <tr><th colspan="2"><h3 style="margin:0">&#x1F4E7; Email</h3></th></tr>
          <tr><th>From (email remitente)</th>
              <td><input class="regular-text" name="notif_email_from" value="<?php echo esc_attr(get_option('psp_notif_email_from',get_option('admin_email'))); ?>"></td></tr>
          <tr><th colspan="2"><h3 style="margin:0">&#x1F4F1; SMS (Twilio)</h3></th></tr>
          <tr><th>Account SID</th><td><input class="regular-text" name="twilio_sid" value="<?php echo esc_attr(get_option('psp_twilio_sid','')); ?>"></td></tr>
          <tr><th>Auth Token</th><td><input class="regular-text" type="password" name="twilio_token" value="<?php echo esc_attr(get_option('psp_twilio_token','')); ?>"></td></tr>
          <tr><th>From (n&uacute;mero Twilio)</th><td><input name="twilio_from" value="<?php echo esc_attr(get_option('psp_twilio_from','')); ?>" placeholder="+15551234567"></td></tr>
          <tr><th colspan="2"><h3 style="margin:0">&#x1F4AC; WhatsApp (360dialog)</h3></th></tr>
          <tr><th>API Token</th><td><input class="regular-text" type="password" name="wa_360_token" value="<?php echo esc_attr(get_option('psp_wa_360_token','')); ?>"></td></tr>
          <tr><th>URL API</th><td><input class="regular-text" name="wa_360_url" value="<?php echo esc_attr(get_option('psp_wa_360_url','https://waba.360dialog.io/v1/messages')); ?>"></td></tr>
        </table>
        <p><button class="button button-primary" name="psp_save_notif">&#x1F4BE; Guardar</button></p>
      </form>

      <hr>
      <h2>&#x1F9EA; Enviar notificaci&oacute;n de prueba</h2>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div><label>Email destino:</label><br>
          <input type="email" id="test-email" class="regular-text" value="<?php echo esc_attr(get_option('admin_email')); ?>"></div>
        <button onclick="testNotif('email')" class="button button-primary">Probar Email</button>
        <div><label>Celular (507XXXXXXXX):</label><br>
          <input type="text" id="test-celular" style="width:160px" placeholder="507XXXXXXXX"></div>
        <button onclick="testNotif('sms')" class="button">Probar SMS</button>
        <button onclick="testNotif('whatsapp')" class="button">Probar WhatsApp</button>
      </div>
      <div id="test-notif-res" style="margin-top:12px;font-size:13px"></div>
    </div>
    <script>
    function testNotif(canal) {
      var dest = canal==='email'?document.getElementById('test-email').value:document.getElementById('test-celular').value;
      var res  = document.getElementById('test-notif-res');
      res.textContent='&#x23F3; Enviando...';
      jQuery.post(ajaxurl,{action:'psp_test_notif',canal,destino:dest,psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'},function(d){
        res.innerHTML = d.success?'&#x2705; Enviado':'&#x274C; Error: '+(d.data&&d.data.message?d.data.message:'');
      });
    }
    </script>
    <?php
}

add_action('wp_ajax_psp_test_notif', function(){
    if(!current_user_can('manage_options')) wp_send_json_error();
    $canal   = sanitize_text_field($_POST['canal']??'email');
    $destino = sanitize_text_field($_POST['destino']??'');
    if (!$destino) wp_send_json_error(['message'=>'Destino requerido']);
    $msg = 'Esta es una notificación de prueba del sistema PSP — Panamá Sin Pobreza.';
    if ($canal==='email') {
        $ok = PSP_Notificaciones::enviarEmail($destino, 'Admin', 'sistema', $msg, []);
        wp_send_json_success(['message'=>'Email enviado']);
    } elseif ($canal==='sms') {
        PSP_Notificaciones::enviarSMS($destino, $msg);
        wp_send_json_success();
    } elseif ($canal==='whatsapp') {
        PSP_Notificaciones::enviarWhatsApp($destino, 'Admin', 'sistema', $msg, []);
        wp_send_json_success();
    }
    wp_send_json_error(['message'=>'Canal no reconocido']);
});
