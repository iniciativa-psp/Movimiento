<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function psp_get_membresias_config() {
    return [
        [ 'tipo'=>'nacional',       'nombre'=>'Miembro Nacional',       'icono'=>'&#x1F1F5;&#x1F1E6;', 'precio_default'=>'5',  'periodo'=>'único',        'descripcion'=>'Para todo panameño que quiere apoyar el movimiento.',   'destacada'=>true,  'calculadora'=>false,
          'beneficios'=>['Código de referido único','Ranking nacional','Grupos WhatsApp territoriales','Certificado digital','Retos y sorteos'] ],
        [ 'tipo'=>'internacional',  'nombre'=>'Miembro Internacional',  'icono'=>'&#x1F30E;',           'precio_default'=>'10', 'periodo'=>'único',        'descripcion'=>'Para panameños residentes en el exterior.',             'destacada'=>false, 'calculadora'=>false,
          'beneficios'=>['Todo lo del Miembro Nacional','Ranking por país','Grupo WhatsApp internacional','Visibilidad como embajador exterior'] ],
        [ 'tipo'=>'actor',          'nombre'=>'Actor / Coalición',       'icono'=>'&#x1F3AD;',           'precio_default'=>'25', 'periodo'=>'único',        'descripcion'=>'Para colectivos, coaliciones y actores sociales.',      'destacada'=>false, 'calculadora'=>false,
          'beneficios'=>['Perfil de colectivo','Código de actor','Dashboard de red','Prioridad en eventos','Mención oficial'] ],
        [ 'tipo'=>'sector',         'nombre'=>'Sector / Empresa',        'icono'=>'&#x1F3E2;',           'precio_default'=>'50', 'periodo'=>'único',        'descripcion'=>'Para empresas, gremios y organizaciones.',             'destacada'=>false, 'calculadora'=>false,
          'beneficios'=>['Logo en directorio','Código sectorial','Informe de impacto','Acceso ERP','Reconocimiento en prensa'] ],
        [ 'tipo'=>'hogar_solidario','nombre'=>'Hogar Solidario',         'icono'=>'&#x1F3E0;',           'precio_default'=>'15', 'periodo'=>'por hogar',    'descripcion'=>'Apoyas directamente a un hogar vulnerable.',           'destacada'=>false, 'calculadora'=>true,
          'beneficios'=>['Apoyo directo a familias','Calculadora de empleos','Certificado de impacto','Seguimiento del hogar','Informe trimestral'] ],
        [ 'tipo'=>'productor',      'nombre'=>'Productor Beneficiario',  'icono'=>'&#x1F33E;',           'precio_default'=>'20', 'periodo'=>'por productor','descripcion'=>'Financia a un productor agropecuario pobre.',          'destacada'=>false, 'calculadora'=>true,
          'beneficios'=>['Empleo directo','Hasta 4 personas salen de pobreza','Productos en red solidaria','Trazabilidad','Factura DGI'] ],
        [ 'tipo'=>'comunicador',    'nombre'=>'Comunicador',             'icono'=>'&#x1F4E2;',           'precio_default'=>'15', 'periodo'=>'único',        'descripcion'=>'Para periodistas, medios y comunicadores.',            'destacada'=>false, 'calculadora'=>false,
          'beneficios'=>['Credencial oficial','Sala de prensa digital','Kits de prensa','Contenido exclusivo'] ],
        [ 'tipo'=>'influencer',     'nombre'=>'Influencer',              'icono'=>'&#x1F4F1;',           'precio_default'=>'25', 'periodo'=>'único',        'descripcion'=>'Para influencers digitales con audiencia relevante.',   'destacada'=>false, 'calculadora'=>false,
          'beneficios'=>['Código exclusivo','Puntos por referido','Contenido personalizado','Ranking visible','Reconocimiento'] ],
        [ 'tipo'=>'embajador',      'nombre'=>'Embajador',               'icono'=>'&#x1F31F;',           'precio_default'=>'0',  'periodo'=>'gratuito',     'descripcion'=>'Coordinador territorial voluntario.',                  'destacada'=>false, 'calculadora'=>false,
          'beneficios'=>['Cargo oficial','Zona territorial','Herramientas de coordinación','Dashboard de equipo','Capacitaciones'] ],
        [ 'tipo'=>'voluntario',     'nombre'=>'Voluntario',              'icono'=>'&#x1F91D;',           'precio_default'=>'0',  'periodo'=>'gratuito',     'descripcion'=>'Contribuye con tiempo y habilidades.',                 'destacada'=>false, 'calculadora'=>false,
          'beneficios'=>['Registro activo','Asignación de tareas','Certificado','Puntos por actividades'] ],
    ];
}

function psp_get_membresias_nombres() {
    $r = [];
    foreach ( psp_get_membresias_config() as $m ) { $r[ $m['tipo'] ] = $m['nombre']; }
    return $r;
}

function psp_get_precios_js() {
    $r = [];
    foreach ( psp_get_membresias_config() as $m ) {
        $r[ $m['tipo'] ] = (float) get_option( 'psp_precio_' . $m['tipo'], $m['precio_default'] );
    }
    return $r;
}
