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

add_shortcode( 'psp_login',    'psp_login_shortcode' );
add_shortcode( 'psp_registro', 'psp_registro_shortcode' );
add_shortcode( 'psp_perfil',   'psp_perfil_shortcode' );

// ── Shortcode Login ───────────────────────────────────────────────────────────
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
