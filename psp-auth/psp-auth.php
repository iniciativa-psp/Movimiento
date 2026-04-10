<?php
/**
 * Plugin Name: PSP Auth
 * Description: Autenticación de miembros vía código/celular/email usando Supabase Auth.
 * Version:     1.0.0
 * Author:      PSP Dev Team
 */
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/auth-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/session.php';

add_shortcode('psp_login',    'psp_login_shortcode');
add_shortcode('psp_registro', 'psp_registro_shortcode');
add_shortcode('psp_perfil',   'psp_perfil_shortcode');

function psp_login_shortcode(): string {
    ob_start(); ?>
    <div id="psp-auth-wrap" class="psp-card">
      <div id="psp-auth-step1">
        <h2>Iniciar sesión</h2>
        <p>Ingresa tu celular o correo</p>
        <input id="psp-auth-identifier" type="text" placeholder="Celular o correo electrónico" class="psp-input">
        <button onclick="PSPAuth.sendOTP()" class="psp-btn psp-btn-primary">Enviar código</button>
      </div>
      <div id="psp-auth-step2" style="display:none">
        <h2>Verificar código</h2>
        <p>Ingresa el código de 6 dígitos enviado a tu celular</p>
        <input id="psp-auth-otp" type="text" placeholder="000000" maxlength="6" class="psp-input psp-otp">
        <button onclick="PSPAuth.verifyOTP()" class="psp-btn psp-btn-primary">Verificar</button>
        <p><small id="psp-auth-msg"></small></p>
      </div>
      <div id="psp-auth-loggedin" style="display:none">
        <p>✅ Sesión iniciada. <a href="#" onclick="PSPAuth.logout()">Cerrar sesión</a></p>
      </div>
    </div>
    <script>
    const PSPAuth = {
      identifier: '',
      sendOTP() {
        this.identifier = document.getElementById('psp-auth-identifier').value;
        if (!this.identifier) return alert('Ingresa tu celular o correo');
        fetch(PSP_CONFIG.ajax_url, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({action:'psp_send_otp', identifier: this.identifier, psp_nonce: PSP_CONFIG.nonce})
        }).then(r=>r.json()).then(d => {
          if (d.success) {
            document.getElementById('psp-auth-step1').style.display = 'none';
            document.getElementById('psp-auth-step2').style.display = 'block';
          } else {
            alert('Error: ' + (d.data?.message || 'Intenta de nuevo'));
          }
        });
      },
      verifyOTP() {
        const otp = document.getElementById('psp-auth-otp').value;
        fetch(PSP_CONFIG.ajax_url, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({action:'psp_verify_otp', identifier: this.identifier, otp, psp_nonce: PSP_CONFIG.nonce})
        }).then(r=>r.json()).then(d => {
          if (d.success) {
            document.cookie = 'psp_jwt=' + d.data.jwt + '; path=/; SameSite=Strict';
            document.getElementById('psp-auth-step2').style.display = 'none';
            document.getElementById('psp-auth-loggedin').style.display = 'block';
            window.dispatchEvent(new CustomEvent('psp:login', {detail: d.data}));
            if (d.data.redirect) location.href = d.data.redirect;
          } else {
            document.getElementById('psp-auth-msg').textContent = '❌ Código incorrecto';
          }
        });
      },
      logout() {
        document.cookie = 'psp_jwt=; max-age=0; path=/';
        location.reload();
      }
    };
    </script>
    <?php return ob_get_clean();
}

function psp_registro_shortcode(): string {
    ob_start(); ?>
    <div id="psp-registro-wrap" class="psp-card">
      <h2>Únete al Movimiento</h2>
      <form id="psp-form-registro">
        <input name="nombre" type="text"   placeholder="Nombre completo *"  class="psp-input" required>
        <input name="celular" type="tel"   placeholder="Celular (507XXXXXXXX) *" class="psp-input" required>
        <input name="email"  type="email"  placeholder="Correo electrónico"  class="psp-input">
        <div id="psp-territorial-selector"></div>
        <input name="codigo_referido" type="text" placeholder="Código de quien te invitó (opcional)" class="psp-input">
        <select name="tipo_miembro" class="psp-input" required>
          <option value="">Tipo de membresía *</option>
          <option value="nacional">Miembro Nacional — $5</option>
          <option value="internacional">Miembro Internacional — $10</option>
          <option value="actor">Actor / Colectivo — $25</option>
          <option value="sector">Sector / Empresa — $50</option>
          <option value="hogar_solidario">Hogar Solidario — $15</option>
          <option value="productor">Productor Beneficiario — $20</option>
        </select>
        <button type="submit" class="psp-btn psp-btn-primary psp-btn-full">🚀 Registrarme y Apoyar</button>
      </form>
      <div id="psp-registro-success" style="display:none">
        <h3>✅ ¡Bienvenido al Movimiento!</h3>
        <p>Tu código PSP: <strong id="psp-mi-codigo"></strong></p>
        <p>Comparte y gana puntos por cada persona que se una.</p>
        <div id="psp-share-btns"></div>
      </div>
    </div>
    <script>
    document.getElementById('psp-form-registro')?.addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = this.querySelector('button[type=submit]');
      btn.textContent = 'Registrando...'; btn.disabled = true;
      const fd = new FormData(this);
      fd.append('action', 'psp_registro');
      fd.append('psp_nonce', PSP_CONFIG.nonce);
      try {
        const r = await fetch(PSP_CONFIG.ajax_url, {method:'POST', body: new URLSearchParams(fd)});
        const d = await r.json();
        if (d.success) {
          this.style.display = 'none';
          document.getElementById('psp-registro-success').style.display = 'block';
          document.getElementById('psp-mi-codigo').textContent = d.data.codigo;
          const link = location.origin + '/?ref=' + d.data.codigo;
          document.getElementById('psp-share-btns').innerHTML = `
            <a href="https://wa.me/?text=${encodeURIComponent('¡Me uní a Panamá Sin Pobreza! Únete aquí: '+link+' Código: '+d.data.codigo)}" target="_blank" class="psp-btn psp-btn-wa">WhatsApp</a>
            <button onclick="navigator.clipboard.writeText('${link}');this.textContent='¡Copiado!'" class="psp-btn">Copiar enlace</button>`;
          if (d.data.jwt) document.cookie = 'psp_jwt=' + d.data.jwt + '; path=/; SameSite=Strict';
        } else {
          alert(d.data?.message || 'Error al registrar');
        }
      } finally { btn.textContent = 'Registrarme'; btn.disabled = false; }
    });
    </script>
    <?php return ob_get_clean();
}

function psp_perfil_shortcode(): string {
    return '<div id="psp-perfil-wrap" class="psp-card"><div id="psp-perfil-contenido">Cargando...</div></div>
    <script>PSPDashboard && PSPDashboard.loadPerfil && PSPDashboard.loadPerfil();</script>';
}
