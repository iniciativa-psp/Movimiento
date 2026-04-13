<?php
/**
 * Plugin Name: PSP Auth
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Autenticación de miembros vía OTP (celular o correo) usando Supabase Auth. Registro completo con referidos y selector territorial.
 * Version:     1.0.2
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-auth
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_notices', 'psp_auth_check_core' );
function psp_auth_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) ) {
        echo '<div class="notice notice-error"><p><strong>PSP Auth:</strong> Requiere <strong>PSP Core</strong> activo.</p></div>';
    }
}

add_action( 'plugins_loaded', 'psp_auth_load' );
function psp_auth_load() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/session.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/auth-handler.php';
}

add_shortcode( 'psp_login',              'psp_login_shortcode' );
add_shortcode( 'psp_registro',           'psp_registro_shortcode' );
add_shortcode( 'psp_perfil',             'psp_perfil_shortcode' );
add_shortcode( 'psp_registro_completo',  'psp_registro_completo_shortcode' );

// ── Capturar ?ref= y persistir en cookie ────────────────────────────────────
add_action( 'init', 'psp_capture_ref_param' );
function psp_capture_ref_param() {
    if ( ! empty( $_GET['ref'] ) ) {
        $codigo = sanitize_text_field( wp_unslash( $_GET['ref'] ) );
        if ( preg_match( '/^[A-Z0-9\-]{5,30}$/', $codigo ) ) {
            setcookie( 'psp_ref', $codigo, time() + 86400 * 30, '/', '', is_ssl(), true );
            $_COOKIE['psp_ref'] = $codigo;
        }
    }
}
function psp_login_shortcode( $atts = [] ) {
    ob_start(); ?>
    <div id="psp-auth-wrap" class="psp-card">
      <div id="psp-auth-step1">
        <h2>Iniciar sesi&oacute;n</h2>
        <p style="color:#555;font-size:14px">Ingresa tu celular o correo electr&oacute;nico</p>
        <input id="psp-auth-id" type="text" placeholder="Celular (507XXXXXXXX) o correo" class="psp-input">
        <button onclick="PSPAuthUI.sendOTP()" class="psp-btn psp-btn-primary psp-btn-full">
          Enviar c&oacute;digo
        </button>
        <div id="psp-auth-msg1" style="margin-top:8px;font-size:13px"></div>
      </div>

      <div id="psp-auth-step2" style="display:none">
        <h2>Verificar c&oacute;digo</h2>
        <p style="color:#555;font-size:13px">Ingresa el c&oacute;digo de 6 d&iacute;gitos enviado a <span id="psp-auth-dest"></span></p>
        <input id="psp-auth-otp" type="text" placeholder="000000" maxlength="6"
               class="psp-input psp-otp" autocomplete="one-time-code">
        <button onclick="PSPAuthUI.verifyOTP()" class="psp-btn psp-btn-primary psp-btn-full">
          Verificar
        </button>
        <p style="margin-top:8px;font-size:12px">
          <a href="#" onclick="PSPAuthUI.reset();return false">&#x2190; Cambiar n&uacute;mero</a>
        </p>
        <div id="psp-auth-msg2" style="margin-top:8px;font-size:13px;color:#c00"></div>
      </div>

      <div id="psp-auth-ok" style="display:none;text-align:center;padding:16px">
        <div style="font-size:40px">&#x2705;</div>
        <p style="font-weight:700;margin-top:8px">&#xa1;Sesi&oacute;n iniciada!</p>
        <a href="#" onclick="PSPAuthUI.logout();return false" style="font-size:13px;color:#888">
          Cerrar sesi&oacute;n
        </a>
      </div>
    </div>

    <script>
    var PSPAuthUI = {
      identifier: '',

      sendOTP: function() {
        var id = document.getElementById('psp-auth-id').value.trim();
        if (!id) { PSPAuthUI.msg(1,'Ingresa tu celular o correo'); return; }
        this.identifier = id;
        PSPAuthUI.msg(1,'&#x23F3; Enviando...');
        fetch(PSP_CONFIG.ajax_url, {
          method: 'POST',
          body: new URLSearchParams({
            action: 'psp_send_otp', identifier: id, psp_nonce: PSP_CONFIG.nonce
          })
        }).then(function(r){return r.json();}).then(function(d) {
          if (d.success) {
            document.getElementById('psp-auth-step1').style.display = 'none';
            document.getElementById('psp-auth-step2').style.display = 'block';
            document.getElementById('psp-auth-dest').textContent = PSPAuthUI.identifier;
            document.getElementById('psp-auth-otp').focus();
          } else {
            PSPAuthUI.msg(1,'&#x274C; ' + ((d.data&&d.data.message)?d.data.message:'Error enviando código'));
          }
        }).catch(function(){ PSPAuthUI.msg(1,'Error de conexión'); });
      },

      verifyOTP: function() {
        var otp = document.getElementById('psp-auth-otp').value.trim();
        if (otp.length < 6) { PSPAuthUI.msg(2,'El código debe tener 6 dígitos'); return; }
        PSPAuthUI.msg(2,'&#x23F3; Verificando...');
        fetch(PSP_CONFIG.ajax_url, {
          method: 'POST',
          body: new URLSearchParams({
            action: 'psp_verify_otp',
            identifier: this.identifier,
            otp: otp,
            psp_nonce: PSP_CONFIG.nonce
          })
        }).then(function(r){return r.json();}).then(function(d) {
          if (d.success) {
            if (typeof PSPCookie !== 'undefined') {
              PSPCookie.set('psp_jwt', d.data.jwt, 30);
              if (d.data.miembro_id) PSPCookie.set('psp_miembro_id', d.data.miembro_id, 30);
            } else {
              document.cookie = 'psp_jwt=' + d.data.jwt + '; path=/; SameSite=Strict; max-age=2592000';
            }
            document.getElementById('psp-auth-step2').style.display = 'none';
            document.getElementById('psp-auth-ok').style.display    = 'block';
            window.dispatchEvent(new CustomEvent('psp:login', {detail: d.data}));
            if (d.data.redirect) setTimeout(function(){ window.location.href = d.data.redirect; }, 800);
          } else {
            PSPAuthUI.msg(2, '&#x274C; Código incorrecto o expirado');
          }
        }).catch(function(){ PSPAuthUI.msg(2,'Error de conexión'); });
      },

      logout: function() {
        if (typeof PSPCookie !== 'undefined') { PSPCookie.del('psp_jwt'); PSPCookie.del('psp_miembro_id'); }
        else { document.cookie='psp_jwt=;max-age=0;path=/'; }
        window.location.reload();
      },

      reset: function() {
        document.getElementById('psp-auth-step1').style.display = 'block';
        document.getElementById('psp-auth-step2').style.display = 'none';
      },

      msg: function(step, txt) {
        var el = document.getElementById('psp-auth-msg' + step);
        if (el) el.innerHTML = txt;
      }
    };

    // Auto-detect if already logged in
    (function(){
      var jwt = typeof PSPCookie!=='undefined' ? PSPCookie.get('psp_jwt')
              : (document.cookie.match(/psp_jwt=([^;]+)/)||[])[1];
      if (jwt) {
        document.getElementById('psp-auth-step1').style.display = 'none';
        document.getElementById('psp-auth-ok').style.display    = 'block';
      }
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── Shortcode Registro ────────────────────────────────────────────────────────
function psp_registro_shortcode( $atts = [] ) {
    // Leer tipo de URL si viene pre-seleccionado
    $tipo_pre = sanitize_text_field( $_GET['tipo'] ?? '' );
    ob_start(); ?>
    <div id="psp-reg-wrap" class="psp-card">
      <h2>&#x1F91D; &Uacute;nete al Movimiento</h2>

      <form id="psp-form-reg" autocomplete="off">

        <input name="nombre" type="text" placeholder="Nombre completo *"
               class="psp-input" required>
        <input name="celular" type="tel" placeholder="Celular (ej: 50766XXXXXX) *"
               class="psp-input" required>
        <input name="email" type="email" placeholder="Correo electr&oacute;nico (opcional)"
               class="psp-input">

        <!-- Selector territorial (usa psp-territorial si está activo, si no inline) -->
        <div id="psp-reg-territorial">
          <?php
          // Si psp-territorial está activo, usar su shortcode
          if ( shortcode_exists('psp_territorial_selector') ) {
              echo do_shortcode('[psp_territorial_selector required="si"]');
          } else {
              // Fallback inline básico
              echo psp_auth_territorial_fallback();
          }
          ?>
        </div>

        <select name="tipo_miembro" class="psp-input" required>
          <option value="">Tipo de membres&iacute;a *</option>
          <?php
          if ( function_exists('psp_get_membresias_config') ) {
              foreach ( psp_get_membresias_config() as $m ) {
                  $precio = (float) get_option('psp_precio_'.$m['tipo'], $m['precio_default']);
                  $label  = $m['icono'] . ' ' . $m['nombre'] . ( $precio > 0 ? ' — $'.number_format($precio,2) : ' — Gratis' );
                  $sel    = ( $tipo_pre === $m['tipo'] ) ? 'selected' : '';
                  echo '<option value="' . esc_attr($m['tipo']) . '" ' . $sel . '>' . $label . '</option>';
              }
          } else {
              $opciones = ['nacional'=>'Miembro Nacional — $5','internacional'=>'Internacional — $10',
                           'actor'=>'Actor / Coalición — $25','sector'=>'Sector / Empresa — $50',
                           'hogar_solidario'=>'Hogar Solidario — $15','productor'=>'Productor — $20'];
              foreach ($opciones as $v=>$l) {
                  echo '<option value="'.esc_attr($v).'"'.($tipo_pre===$v?' selected':'').'>'.esc_html($l).'</option>';
              }
          }
          ?>
        </select>

        <input name="codigo_referido" type="text"
               placeholder="C&oacute;digo de quien te invit&oacute; (opcional)"
               class="psp-input"
               value="<?php echo esc_attr( $_COOKIE['psp_ref'] ?? $_GET['ref'] ?? '' ); ?>">

        <button type="submit" class="psp-btn psp-btn-primary psp-btn-full"
                style="font-size:16px;padding:14px">
          &#x1F680; Registrarme ahora
        </button>
      </form>

      <div id="psp-reg-ok" style="display:none;text-align:center;padding:20px">
        <div style="font-size:48px">&#x1F389;</div>
        <h3 style="color:#0B5E43;margin-top:12px">&#xa1;Bienvenido al Movimiento!</h3>
        <p>Tu c&oacute;digo PSP:</p>
        <div id="psp-reg-codigo"
             style="font-size:28px;font-weight:900;color:#0B5E43;font-family:monospace;letter-spacing:.12em;
                    padding:14px;background:#F0FDF4;border-radius:10px;margin:12px 0"></div>
        <p style="font-size:13px;color:#555">Comp&aacute;rtelo y gana puntos por cada persona que se una.</p>
        <div id="psp-reg-share" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:12px"></div>
        <div style="margin-top:16px">
          <a id="psp-reg-pago-btn" href="/apoyar/" class="psp-btn psp-btn-primary">
            &#x1F4B3; Completar mi pago
          </a>
        </div>
      </div>
    </div>

    <script>
    document.getElementById('psp-form-reg')?.addEventListener('submit', async function(e) {
      e.preventDefault();
      var btn = this.querySelector('button[type=submit]');
      btn.textContent = 'Registrando...'; btn.disabled = true;

      var fd = new FormData(this);
      fd.append('action',    'psp_registro');
      fd.append('psp_nonce', PSP_CONFIG.nonce);

      try {
        var r = await fetch(PSP_CONFIG.ajax_url, {method:'POST', body: new URLSearchParams(fd)});
        var d = await r.json();

        if (d.success) {
          document.getElementById('psp-form-reg').style.display = 'none';
          document.getElementById('psp-reg-ok').style.display   = 'block';
          document.getElementById('psp-reg-codigo').textContent  = d.data.codigo;

          if (d.data.jwt) {
            if (typeof PSPCookie !== 'undefined') {
              PSPCookie.set('psp_jwt', d.data.jwt, 30);
              PSPCookie.set('psp_miembro_id', d.data.miembro_id, 30);
            } else {
              document.cookie = 'psp_jwt='      + d.data.jwt       + '; path=/; SameSite=Strict; max-age=2592000';
              document.cookie = 'psp_miembro_id='+ d.data.miembro_id+ '; path=/; SameSite=Strict; max-age=2592000';
            }
          }

          var link  = location.origin + '/?ref=' + d.data.codigo;
          var texto = '¡Me uní a Panamá Sin Pobreza! ¡Únete tú también! ' + link;
          document.getElementById('psp-reg-share').innerHTML = [
            ['https://wa.me/?text='+encodeURIComponent(texto), '&#x1F4AC; WhatsApp',  '#25D366'],
            ['https://t.me/share/url?url='+encodeURIComponent(link)+'&text='+encodeURIComponent('¡Únete a #PanamáSinPobreza!'), '&#x2708;&#xFE0F; Telegram', '#229ED9'],
            ['https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(link), '&#x1F310; Facebook', '#1877F2'],
          ].map(function(s){
            return '<a href="'+s[0]+'" target="_blank" style="background:'+s[2]+';color:#fff;padding:8px 14px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:700">'+s[1]+'</a>';
          }).join('');

          // Actualizar botón de pago con tipo
          var pagoBtn = document.getElementById('psp-reg-pago-btn');
          if (pagoBtn && d.data.tipo) pagoBtn.href = '/apoyar/?tipo=' + d.data.tipo;

          if (typeof PSPToast !== 'undefined') PSPToast.show('¡Registro exitoso! Bienvenido 🎉');
        } else {
          if (typeof PSPToast !== 'undefined')
            PSPToast.show((d.data&&d.data.message?d.data.message:'Error al registrar'), 'error');
          else alert(d.data&&d.data.message?d.data.message:'Error al registrar');
        }
      } catch(err) {
        alert('Error de conexión. Intenta de nuevo.');
      } finally {
        btn.textContent = 'Registrarme ahora'; btn.disabled = false;
      }
    });
    </script>
    <?php
    return ob_get_clean();
}

// ── Fallback territorial si psp-territorial no está activo ────────────────────
function psp_auth_territorial_fallback() {
    ob_start(); ?>
    <div class="psp-terr-toggle" style="display:flex;gap:16px;margin-bottom:12px;font-size:14px;font-weight:600">
      <label><input type="radio" name="ubicacion_tipo" value="PA" checked
                    onchange="PSPRegTerr.toggle('PA')"> &#x1F1F5;&#x1F1E6; Soy de Panam&aacute;</label>
      <label><input type="radio" name="ubicacion_tipo" value="INT"
                    onchange="PSPRegTerr.toggle('INT')"> &#x1F30E; Soy internacional</label>
    </div>
    <div id="psp-reg-panama">
      <select name="provincia_id" id="psp_provincia" class="psp-input" required
              onchange="PSPRegTerr.loadDistritos(this.value)">
        <option value="">-- Selecciona provincia --</option>
      </select>
      <div id="psp-reg-row-distrito" style="display:none;margin-top:8px">
        <select name="distrito_id" id="psp_distrito" class="psp-input"
                onchange="PSPRegTerr.loadCorregimientos(this.value)">
          <option value="">-- Selecciona distrito --</option>
        </select>
      </div>
      <div id="psp-reg-row-corr" style="display:none;margin-top:8px">
        <select name="corregimiento_id" id="psp_corregimiento" class="psp-input"
                onchange="PSPRegTerr.loadComunidades(this.value)">
          <option value="">-- Selecciona corregimiento --</option>
        </select>
      </div>
      <div id="psp-reg-row-com" style="display:none;margin-top:8px">
        <select name="comunidad_id" id="psp_comunidad" class="psp-input">
          <option value="">-- Comunidad (opcional) --</option>
        </select>
      </div>
    </div>
    <div id="psp-reg-inter" style="display:none">
      <select name="pais_id" class="psp-input">
        <option value="">-- Pa&iacute;s --</option>
        <?php
        if ( function_exists('psp_terr_opciones_paises') ) {
            echo psp_terr_opciones_paises();
        } else {
            echo '<option value="US">Estados Unidos</option><option value="ES">España</option><option value="MX">México</option><option value="CO">Colombia</option><option value="ZZ">Otro</option>';
        }
        ?>
      </select>
      <input type="text" name="ciudad" class="psp-input" placeholder="Ciudad" style="margin-top:8px">
    </div>
    <script>
    var PSPRegTerr = {
      toggle: function(tipo) {
        document.getElementById('psp-reg-panama').style.display = tipo==='PA'?'block':'none';
        document.getElementById('psp-reg-inter').style.display  = tipo!=='PA'?'block':'none';
      },
      // Usa psp_terr_get (psp-territorial) si está disponible
      getData: function(tipo, parentId, callback) {
        fetch(PSP_CONFIG.ajax_url, {
          method:'POST',
          body: new URLSearchParams({
            action    : 'psp_terr_get',
            tipo      : tipo,
            parent_id : parentId || '',
            psp_nonce : PSP_CONFIG.nonce
          })
        }).then(function(r){return r.json();}).then(function(d){
          callback(d.success && d.data ? d.data : []);
        }).catch(function(){ callback([]); });
      },
      fill: function(selId, items, ph) {
        var sel = document.getElementById(selId);
        if (!sel) return;
        sel.innerHTML = '<option value="">'+ph+'</option>';
        items.forEach(function(i){ var o=document.createElement('option'); o.value=i.id; o.textContent=i.nombre; sel.appendChild(o); });
        sel.disabled = items.length===0;
      },
      loadProvincias: function() {
        PSPRegTerr.getData('provincias', null, function(items){
          PSPRegTerr.fill('psp_provincia', items, '-- Selecciona provincia --');
        });
      },
      loadDistritos: function(pid) {
        ['psp-reg-row-distrito','psp-reg-row-corr','psp-reg-row-com'].forEach(function(id){
          var el=document.getElementById(id); if(el) el.style.display='none';
        });
        if (!pid) return;
        PSPRegTerr.getData('distritos', pid, function(items){
          PSPRegTerr.fill('psp_distrito', items, '-- Selecciona distrito --');
          var el=document.getElementById('psp-reg-row-distrito'); if(el) el.style.display='block';
        });
      },
      loadCorregimientos: function(did) {
        ['psp-reg-row-corr','psp-reg-row-com'].forEach(function(id){
          var el=document.getElementById(id); if(el) el.style.display='none';
        });
        if (!did) return;
        PSPRegTerr.getData('corregimientos', did, function(items){
          PSPRegTerr.fill('psp_corregimiento', items, '-- Selecciona corregimiento --');
          var el=document.getElementById('psp-reg-row-corr'); if(el) el.style.display='block';
        });
      },
      loadComunidades: function(cid) {
        if (!cid) return;
        PSPRegTerr.getData('comunidades', cid, function(items){
          if (!items.length) return;
          PSPRegTerr.fill('psp_comunidad', items, '-- Comunidad (opcional) --');
          var el=document.getElementById('psp-reg-row-com'); if(el) el.style.display='block';
        });
      }
    };
    document.addEventListener('DOMContentLoaded', function(){ PSPRegTerr.loadProvincias(); });
    </script>
    <?php
    return ob_get_clean();
}

function psp_perfil_shortcode( $atts = [] ) {
    return '<div id="psp-perfil-wrap" class="psp-card">'
        . ( shortcode_exists('psp_mi_membresia') ? do_shortcode('[psp_mi_membresia]') : '<p>Cargando perfil...</p>' )
        . ( shortcode_exists('psp_mi_referido')  ? do_shortcode('[psp_mi_referido]')  : '' )
        . '</div>';
}

/**
 * Shortcode: flujo completo de registro + pago B/.1 + confirmación
 * Uso: [psp_registro_completo]
 *
 * Pasos:
 *   1. Formulario de registro (nombre, celular, email, territorio)
 *   2. Selección y confirmación de pago B/.1 (Yappy, transferencia, etc.)
 *   3. Pantalla de éxito con enlace de referido + botón WhatsApp
 */
function psp_registro_completo_shortcode( $atts = [] ): string {
    $atts = shortcode_atts( [
        'redirect_url' => '',
    ], $atts );

    $ref_cookie  = sanitize_text_field( $_COOKIE['psp_ref'] ?? '' );
    $ref_get     = sanitize_text_field( $_GET['ref']        ?? '' );
    $ref_inicial = $ref_cookie ?: $ref_get;

    $fee      = defined('PSP_MEMBERSHIP_FEE') ? PSP_MEMBERSHIP_FEE : 1.00;
    $fee_fmt  = number_format( $fee, 2 );

    // Datos de pago desde opciones de WP
    $yappy_num   = get_option( 'psp_yappy_numero', '' );
    $yappy_nom   = get_option( 'psp_yappy_nombre', 'Panamá Sin Pobreza' );
    $banco_cuenta = get_option( 'psp_banco_cuenta', '' );
    $banco_nombre = get_option( 'psp_banco_nombre', '' );
    $banco_titular= get_option( 'psp_banco_titular', '' );
    $paypal_email = get_option( 'psp_paypal_email', '' );

    ob_start();
    ?>
    <div id="psp-rc-wrap" class="psp-card psp-registro-completo" style="max-width:520px;margin:0 auto">

      <!-- Pasos visuales -->
      <div class="psp-rc-steps" style="display:flex;gap:0;margin-bottom:24px">
        <?php foreach (['Registro','Pago','¡Listo!'] as $i => $label): ?>
        <div class="psp-rc-step <?php echo $i === 0 ? 'active' : ''; ?>"
             id="psp-rc-step-<?php echo $i; ?>"
             style="flex:1;text-align:center;padding:8px 4px;font-size:12px;font-weight:700;border-bottom:3px solid <?php echo $i === 0 ? '#0B5E43' : '#E5E7EB'; ?>;color:<?php echo $i === 0 ? '#0B5E43' : '#9CA3AF'; ?>">
          <span style="display:block;font-size:18px"><?php echo ['1️⃣','2️⃣','✅'][$i]; ?></span>
          <?php echo esc_html($label); ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ═══════════════ PASO 1: REGISTRO ═══════════════ -->
      <div id="psp-rc-panel-0">
        <h3 style="margin-top:0;color:#0B5E43">&#x1F1F5;&#x1F1E6; &Uacute;nete al Movimiento</h3>
        <p style="font-size:13px;color:#6B7280;margin-bottom:18px">
          Completa los datos. La membres&iacute;a tiene un aporte de
          <strong>B/.<?php echo esc_html($fee_fmt); ?></strong>.
        </p>
        <div id="psp-rc-error-0" class="psp-alert psp-alert-error" style="display:none"></div>

        <div class="psp-field">
          <label class="psp-label">Nombre completo <span style="color:red">*</span></label>
          <input type="text" id="psp-rc-nombre" class="psp-input" placeholder="Tu nombre completo" required>
        </div>
        <div class="psp-field">
          <label class="psp-label">Celular (con código de país) <span style="color:red">*</span></label>
          <input type="tel" id="psp-rc-celular" class="psp-input" placeholder="+50761234567" required>
        </div>
        <div class="psp-field">
          <label class="psp-label">Correo electr&oacute;nico</label>
          <input type="email" id="psp-rc-email" class="psp-input" placeholder="tu@correo.com">
          <span style="font-size:11px;color:#6B7280">Opcional, para recibir link m&aacute;gico de inicio de sesi&oacute;n.</span>
        </div>

        <!-- Selector territorial (usa psp-territorial si está activo) -->
        <div class="psp-field">
          <label class="psp-label">Ubicaci&oacute;n</label>
          <?php if ( shortcode_exists('psp_territorial_selector') ): ?>
            <?php echo do_shortcode('[psp_territorial_selector required="si" prefix="rc_"]'); ?>
          <?php else: ?>
            <div class="psp-terr-fallback">
              <select id="rc_pais_id" class="psp-input" onchange="PSPRegComp.switchPais(this.value)">
                <option value="PA" selected>&#x1F1F5;&#x1F1E6; Panam&aacute;</option>
                <option value="">&#x1F30E; Internacional</option>
              </select>
              <input type="text" id="rc_ciudad" class="psp-input" placeholder="Ciudad" style="margin-top:8px;display:none">
            </div>
          <?php endif; ?>
        </div>

        <input type="hidden" id="psp-rc-ref" value="<?php echo esc_attr($ref_inicial); ?>">

        <button id="psp-rc-btn-0" class="psp-btn psp-btn-primary psp-btn-block psp-btn-lg"
                onclick="PSPRegComp.paso1()" style="margin-top:8px">
          Continuar al pago &rarr;
        </button>
        <p style="font-size:11px;text-align:center;color:#9CA3AF;margin-top:10px">
          Al continuar aceptas los <a href="/terminos" target="_blank">t&eacute;rminos del movimiento</a>.
        </p>
      </div>

      <!-- ═══════════════ PASO 2: PAGO ═══════════════ -->
      <div id="psp-rc-panel-1" style="display:none">
        <h3 style="margin-top:0;color:#0B5E43">&#x1F4B3; Confirma tu Membres&iacute;a</h3>
        <p style="font-size:13px;color:#6B7280;margin-bottom:4px">
          Realiza un aporte de <strong style="font-size:16px;color:#0B5E43">B/.<?php echo esc_html($fee_fmt); ?></strong>
          a trav&eacute;s de cualquiera de estos m&eacute;todos:
        </p>
        <div id="psp-rc-error-1" class="psp-alert psp-alert-error" style="display:none"></div>

        <!-- Tabs de métodos de pago -->
        <div class="psp-pay-tabs" style="display:flex;flex-wrap:wrap;gap:6px;margin:14px 0">
          <?php
          $metodos = [
            'yappy'                    => ['emoji' => '📱', 'label' => 'Yappy'],
            'clave'                    => ['emoji' => '🔑', 'label' => 'Clave'],
            'tarjeta_bg'               => ['emoji' => '💳', 'label' => 'Tarjeta BG'],
            'puntopago'                => ['emoji' => '🏧', 'label' => 'PuntoPago'],
            'paypal'                   => ['emoji' => '🅿️', 'label' => 'PayPal'],
            'transferencia_nacional'   => ['emoji' => '🏦', 'label' => 'Transferencia'],
            'transferencia_internacional' => ['emoji' => '🌐', 'label' => 'SWIFT'],
            'efectivo'                 => ['emoji' => '💵', 'label' => 'Efectivo'],
          ];
          foreach ( $metodos as $key => $m ):
          ?>
          <button class="psp-pay-tab" data-metodo="<?php echo esc_attr($key); ?>"
                  onclick="PSPRegComp.selectMetodo('<?php echo esc_js($key); ?>')"
                  style="padding:8px 12px;border:2px solid #E5E7EB;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;font-weight:600">
            <?php echo esc_html($m['emoji'] . ' ' . $m['label']); ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Instrucciones por método -->
        <div id="psp-pay-instrucciones" style="background:#F0FDF4;border-radius:10px;padding:16px;margin-bottom:16px;display:none">

          <div class="psp-pi" id="psp-pi-yappy" style="display:none">
            <?php if ($yappy_num): ?>
            <p>&#x1F4F1; Env&iacute;a <strong>B/.<?php echo esc_html($fee_fmt); ?></strong> v&iacute;a Yappy al n&uacute;mero:</p>
            <p style="font-size:22px;font-weight:700;color:#0B5E43"><?php echo esc_html($yappy_num); ?></p>
            <p style="font-size:13px">Nombre: <strong><?php echo esc_html($yappy_nom); ?></strong></p>
            <p style="font-size:12px;color:#6B7280">Coloca tu nombre en el mensaje del pago.</p>
            <?php else: ?>
            <p>&#x26A0;&#xFE0F; Yappy no est&aacute; configurado a&uacute;n. Contacta al equipo para obtener el n&uacute;mero.</p>
            <?php endif; ?>
          </div>

          <div class="psp-pi" id="psp-pi-clave" style="display:none">
            <p>&#x1F511; Disponible pr&oacute;ximamente v&iacute;a Sistema Clave (PagueloFacil).</p>
            <p style="font-size:12px;color:#6B7280">Por ahora usa transferencia bancaria o Yappy.</p>
          </div>

          <div class="psp-pi" id="psp-pi-tarjeta_bg" style="display:none">
            <p>&#x1F4B3; Pago con Tarjeta (Banco General) — Disponible pr&oacute;ximamente.</p>
            <p style="font-size:12px;color:#6B7280">Por ahora usa transferencia bancaria o Yappy.</p>
          </div>

          <div class="psp-pi" id="psp-pi-puntopago" style="display:none">
            <p>&#x1F3E7; Pago en puntos PuntoPago — Disponible pr&oacute;ximamente.</p>
          </div>

          <div class="psp-pi" id="psp-pi-paypal" style="display:none">
            <?php if ($paypal_email): ?>
            <p>&#x1F4B8; Env&iacute;a <strong>B/.<?php echo esc_html($fee_fmt); ?></strong> por PayPal a:</p>
            <p style="font-size:18px;font-weight:700;color:#0B5E43"><?php echo esc_html($paypal_email); ?></p>
            <p style="font-size:12px;color:#6B7280">Selecciona "Pago a amigos y familiares" para evitar comisiones.</p>
            <?php else: ?>
            <p>&#x26A0;&#xFE0F; PayPal no configurado a&uacute;n.</p>
            <?php endif; ?>
          </div>

          <div class="psp-pi" id="psp-pi-transferencia_nacional" style="display:none">
            <?php if ($banco_cuenta): ?>
            <p>&#x1F3E6; Transferencia Bancaria Nacional (ACH/Banca en l&iacute;nea):</p>
            <table style="font-size:13px;width:100%">
              <tr><td><strong>Banco:</strong></td><td><?php echo esc_html($banco_nombre); ?></td></tr>
              <tr><td><strong>Cuenta:</strong></td><td><?php echo esc_html($banco_cuenta); ?></td></tr>
              <tr><td><strong>Titular:</strong></td><td><?php echo esc_html($banco_titular); ?></td></tr>
            </table>
            <?php else: ?>
            <p>&#x26A0;&#xFE0F; Datos bancarios no configurados a&uacute;n. Contacta al equipo.</p>
            <?php endif; ?>
          </div>

          <div class="psp-pi" id="psp-pi-transferencia_internacional" style="display:none">
            <p>&#x1F30D; Transferencia Internacional (SWIFT/IBAN).</p>
            <p style="font-size:12px;color:#6B7280">Contacta al equipo para obtener los datos SWIFT.</p>
          </div>

          <div class="psp-pi" id="psp-pi-efectivo" style="display:none">
            <p>&#x1F4B5; Pago en Efectivo — Contacta a tu coordinador territorial para coordinar el pago.</p>
          </div>

        </div>

        <!-- Referencia de comprobante -->
        <div class="psp-field">
          <label class="psp-label">Referencia / N&uacute;mero de comprobante (opcional)</label>
          <input type="text" id="psp-rc-referencia" class="psp-input" placeholder="Ej: 00123456 o número de transacción">
        </div>

        <div style="display:flex;gap:10px;margin-top:16px">
          <button onclick="PSPRegComp.volverPaso1()" class="psp-btn psp-btn-secondary"
                  style="flex:1">&larr; Atr&aacute;s</button>
          <button id="psp-rc-btn-1" onclick="PSPRegComp.paso2()" class="psp-btn psp-btn-primary"
                  style="flex:2">Confirmar pago &#x2713;</button>
        </div>
        <p style="font-size:11px;text-align:center;color:#9CA3AF;margin-top:8px">
          Tu membres&iacute;a se activar&aacute; tras verificar el pago (puede tomar hasta 24 h para transferencias manuales).
        </p>
      </div>

      <!-- ═══════════════ PASO 3: ÉXITO ═══════════════ -->
      <div id="psp-rc-panel-2" style="display:none;text-align:center">
        <div style="font-size:64px;margin-bottom:8px">🎉</div>
        <h3 style="color:#0B5E43;margin-top:0">&#xa1;Registro Exitoso!</h3>
        <p id="psp-rc-success-msg" style="color:#374151;font-size:14px"></p>

        <!-- Referral link -->
        <div style="background:#F0FDF4;border:2px dashed #0B5E43;border-radius:10px;padding:16px;margin:16px 0">
          <p style="font-size:13px;font-weight:700;color:#065F46;margin:0 0 8px">&#x1F517; Tu enlace personal de referido:</p>
          <input type="text" id="psp-rc-reflink" class="psp-input" readonly
                 style="font-size:12px;text-align:center;background:#fff"
                 onclick="this.select()">
          <div style="display:flex;gap:8px;margin-top:10px">
            <button onclick="PSPRegComp.copiarLink()" class="psp-btn psp-btn-secondary" style="flex:1">
              &#x1F4CB; Copiar
            </button>
            <button onclick="PSPRegComp.compartirWA()" class="psp-btn psp-btn-wa" style="flex:2;background:#25D366;color:#fff">
              &#x1F4F2; Compartir por WhatsApp
            </button>
          </div>
        </div>

        <p style="font-size:13px;color:#6B7280">
          Por cada persona que se registre con tu enlace, ganar&aacute;s puntos y subir&aacute;s en el ranking.
        </p>

        <?php if ($atts['redirect_url']): ?>
        <a href="<?php echo esc_url($atts['redirect_url']); ?>" class="psp-btn psp-btn-primary psp-btn-block"
           style="margin-top:12px">Ver mi cuenta &rarr;</a>
        <?php else: ?>
        <a href="<?php echo esc_url(home_url('/mi-cuenta')); ?>" class="psp-btn psp-btn-primary psp-btn-block"
           style="margin-top:12px">Ver mi cuenta &rarr;</a>
        <?php endif; ?>
      </div>

    </div><!-- /#psp-rc-wrap -->

    <style>
    .psp-registro-completo .psp-field{margin-bottom:14px}
    .psp-registro-completo .psp-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px}
    .psp-registro-completo .psp-input{width:100%;box-sizing:border-box;padding:10px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px}
    .psp-registro-completo .psp-input:focus{outline:none;border-color:#0B5E43;box-shadow:0 0 0 3px rgba(11,94,67,.15)}
    .psp-registro-completo .psp-alert-error{background:#FEF2F2;color:#991B1B;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px}
    .psp-registro-completo .psp-btn-block{display:block;width:100%;text-align:center}
    .psp-registro-completo .psp-btn-lg{padding:13px;font-size:16px;font-weight:700}
    .psp-pay-tab.selected{border-color:#0B5E43 !important;background:#F0FDF4 !important;color:#065F46}
    .psp-rc-step.active{border-bottom-color:#0B5E43 !important;color:#0B5E43 !important}
    </style>

    <script>
    (function() {
      var state = {
        miembro_id: null,
        codigo: null,
        metodo: null,
        nombre: null,
      };

      window.PSPRegComp = {

        setStep: function(n) {
          [0,1,2].forEach(function(i){
            var panel = document.getElementById('psp-rc-panel-' + i);
            var step  = document.getElementById('psp-rc-step-' + i);
            if (panel) panel.style.display = (i === n) ? '' : 'none';
            if (step) {
              step.style.borderBottomColor = (i <= n) ? '#0B5E43' : '#E5E7EB';
              step.style.color             = (i <= n) ? '#0B5E43' : '#9CA3AF';
            }
          });
        },

        paso1: function() {
          var nombre  = document.getElementById('psp-rc-nombre').value.trim();
          var celular = document.getElementById('psp-rc-celular').value.trim();
          var email   = document.getElementById('psp-rc-email').value.trim();
          var ref     = document.getElementById('psp-rc-ref').value;

          var err = document.getElementById('psp-rc-error-0');
          if (!nombre || !celular) {
            err.textContent = 'Nombre y celular son obligatorios.';
            err.style.display = 'block'; return;
          }
          err.style.display = 'none';

          var btn = document.getElementById('psp-rc-btn-0');
          btn.disabled = true; btn.textContent = 'Registrando...';

          // Recopilar territorio
          var provincia_id     = document.getElementById('rc_psp_provincia')    ? document.getElementById('rc_psp_provincia').value    : '';
          var distrito_id      = document.getElementById('rc_psp_distrito')     ? document.getElementById('rc_psp_distrito').value     : '';
          var corregimiento_id = document.getElementById('rc_psp_corregimiento')? document.getElementById('rc_psp_corregimiento').value: '';
          var comunidad_id     = document.getElementById('rc_psp_comunidad')    ? document.getElementById('rc_psp_comunidad').value    : '';
          var pais_id          = document.getElementById('rc_pais_id')          ? document.getElementById('rc_pais_id').value          : 'PA';
          var ciudad           = document.getElementById('rc_ciudad')           ? document.getElementById('rc_ciudad').value           : '';

          var body = new FormData();
          body.append('action',           'psp_registro');
          body.append('psp_nonce',        PSP_CONFIG.nonce);
          body.append('nombre',           nombre);
          body.append('celular',          celular);
          body.append('email',            email);
          body.append('provincia_id',     provincia_id);
          body.append('distrito_id',      distrito_id);
          body.append('corregimiento_id', corregimiento_id);
          body.append('comunidad_id',     comunidad_id);
          body.append('pais_id',          pais_id || 'PA');
          body.append('ciudad',           ciudad);
          body.append('codigo_referido',  ref);

          fetch(PSP_CONFIG.ajax_url, {method:'POST', body: body})
            .then(function(r){ return r.json(); })
            .then(function(d) {
              btn.disabled = false; btn.textContent = 'Continuar al pago →';
              if (!d.success) {
                err.textContent = d.data && d.data.message ? d.data.message : 'Error al registrar.';
                err.style.display = 'block'; return;
              }
              state.miembro_id = d.data.miembro_id;
              state.codigo     = d.data.codigo;
              state.nombre     = nombre;
              PSPRegComp.setStep(1);
            })
            .catch(function(){ btn.disabled = false; btn.textContent = 'Continuar al pago →'; err.textContent = 'Error de conexión.'; err.style.display='block'; });
        },

        selectMetodo: function(metodo) {
          state.metodo = metodo;
          document.querySelectorAll('.psp-pay-tab').forEach(function(b){ b.classList.remove('selected'); });
          var tab = document.querySelector('.psp-pay-tab[data-metodo="' + metodo + '"]');
          if (tab) tab.classList.add('selected');
          document.querySelectorAll('.psp-pi').forEach(function(el){ el.style.display='none'; });
          var pi = document.getElementById('psp-pi-' + metodo);
          if (pi) pi.style.display = 'block';
          document.getElementById('psp-pay-instrucciones').style.display = 'block';
        },

        volverPaso1: function() { PSPRegComp.setStep(0); },

        paso2: function() {
          var err = document.getElementById('psp-rc-error-1');
          if (!state.metodo) {
            err.textContent = 'Selecciona un método de pago.';
            err.style.display = 'block'; return;
          }
          err.style.display = 'none';

          var btn = document.getElementById('psp-rc-btn-1');
          btn.disabled = true; btn.textContent = 'Procesando...';

          var referencia = document.getElementById('psp-rc-referencia').value.trim();
          var body = new FormData();
          body.append('action',     'psp_registrar_pago_membresia');
          body.append('psp_nonce',  PSP_CONFIG.nonce);
          body.append('miembro_id', state.miembro_id);
          body.append('metodo',     state.metodo);
          body.append('monto',      PSP_CONFIG.membership_fee || 1.00);
          body.append('referencia', referencia);

          fetch(PSP_CONFIG.ajax_url, {method:'POST', body: body})
            .then(function(r){ return r.json(); })
            .then(function(d) {
              btn.disabled = false; btn.textContent = 'Confirmar pago ✓';
              if (!d.success) {
                err.textContent = d.data && d.data.message ? d.data.message : 'Error al registrar pago.';
                err.style.display = 'block'; return;
              }
              var reflink = location.origin + '/?ref=' + encodeURIComponent(state.codigo);
              document.getElementById('psp-rc-reflink').value = reflink;
              document.getElementById('psp-rc-success-msg').innerHTML =
                '¡Bienvenido/a, <strong>' + state.nombre + '</strong>! ' + d.data.mensaje;
              PSPRegComp.setStep(2);
            })
            .catch(function(){ btn.disabled = false; btn.textContent = 'Confirmar pago ✓'; err.textContent = 'Error de conexión.'; err.style.display='block'; });
        },

        copiarLink: function() {
          var link = document.getElementById('psp-rc-reflink').value;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(function(){
              alert('¡Enlace copiado al portapapeles!');
            }).catch(function(){
              PSPRegComp._copiarFallback(link);
            });
          } else {
            PSPRegComp._copiarFallback(link);
          }
        },

        _copiarFallback: function(text) {
          var inp = document.getElementById('psp-rc-reflink');
          inp.select(); inp.setSelectionRange(0, 99999);
          try { document.execCommand('copy'); alert('¡Enlace copiado!'); }
          catch (e) { alert('Copia manualmente: ' + text); }
        },

        compartirWA: function() {
          var link = document.getElementById('psp-rc-reflink').value;
          var msg  = '🇵🇦 ¡Me uní al Movimiento Panamá Sin Pobreza! Juntos vamos a erradicar la pobreza. Regístrate aquí: ' + link;
          window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank');
        },

        switchPais: function(val) {
          var ciudadField = document.getElementById('rc_ciudad');
          if (ciudadField) ciudadField.style.display = val ? 'none' : 'block';
        },
      };
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── AJAX: registrar pago de membresía (desde el flujo psp_registro_completo) ──
add_action('wp_ajax_psp_registrar_pago_membresia',        'psp_ajax_registrar_pago_membresia');
add_action('wp_ajax_nopriv_psp_registrar_pago_membresia', 'psp_ajax_registrar_pago_membresia');
function psp_ajax_registrar_pago_membresia(): void {
    if (!psp_verify_nonce()) {
        wp_send_json_error(['message' => 'Sesión inválida']);
    }

    if (!class_exists('PSP_Supabase')) {
        wp_send_json_error(['message' => 'Sistema no disponible']);
    }

    $miembro_id = sanitize_text_field($_POST['miembro_id'] ?? '');
    $metodo     = sanitize_text_field($_POST['metodo']     ?? '');
    $monto      = (float)($_POST['monto'] ?? 1.00);
    $referencia = sanitize_text_field($_POST['referencia'] ?? '');

    if (!$miembro_id || !$metodo) {
        wp_send_json_error(['message' => 'Datos incompletos']);
    }

    $metodos_validos = ['yappy','clave','tarjeta_bg','puntopago','paypal','transferencia_nacional','transferencia_internacional','efectivo'];
    if (!in_array($metodo, $metodos_validos, true)) {
        wp_send_json_error(['message' => 'Método de pago no válido']);
    }

    $fee_min = defined('PSP_MEMBERSHIP_FEE') ? PSP_MEMBERSHIP_FEE : 1.00;
    if ($monto < $fee_min) {
        wp_send_json_error(['message' => sprintf('El monto mínimo es B/.%.2f', $fee_min)]);
    }

    // Verificar que el miembro existe
    $miembro = PSP_Supabase::select('miembros', ['id' => 'eq.' . $miembro_id, 'limit' => 1]);
    if (!$miembro) {
        wp_send_json_error(['message' => 'Miembro no encontrado']);
    }

    $metodos_manuales = ['transferencia_nacional', 'transferencia_internacional', 'efectivo'];
    $estado_pago = in_array($metodo, $metodos_manuales, true) ? 'pendiente_verificacion' : 'pendiente';

    $pago = PSP_Supabase::insert('pagos', [
        'miembro_id' => $miembro_id,
        'tenant_id'  => get_option('psp_tenant_id', 'panama'),
        'monto'      => $monto,
        'moneda'     => 'USD',
        'metodo'     => $metodo,
        'referencia' => $referencia ?: null,
        'estado'     => $estado_pago,
        'concepto'   => 'membresia',
    ], true);

    if (!$pago) {
        wp_send_json_error(['message' => 'Error registrando pago. Intenta de nuevo.']);
    }

    $mensaje = in_array($metodo, $metodos_manuales, true)
        ? '¡Pago registrado! Será verificado y tu membresía se activará en máximo 24 horas.'
        : '¡Pago registrado! Tu membresía se activará en breve.';

    if (function_exists('psp_audit_log')) {
        psp_audit_log('pago_membresia_registrado', ['metodo' => $metodo, 'monto' => $monto], $miembro_id);
    }

    wp_send_json_success(['mensaje' => $mensaje, 'pago_id' => $pago[0]['id']]);
}
