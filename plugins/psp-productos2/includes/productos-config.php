<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function psp_get_productos_config() {
    return [
        [
            'slug'            => 'plantones',
            'nombre'          => 'Plantones de Reforestación',
            'categoria'       => 'plantones',
            'categoria_label' => '&#x1F331; Reforestación',
            'icono'           => '&#x1F331;',
            'descripcion_corta' => 'Siembra árboles en Panamá, genera empleo rural y compensa tu huella de carbono. Desde $2 por plantón.',
            'tipo_precio'     => 'unitario',
            'opcion_precio'   => 'psp_precio_planton',
            'precio_default'  => '2',
            'unidad'          => '/ plantón',
            'destacado'       => false,
            'nuevo'           => false,
            'boton_texto'     => '&#x1F331; Comprar plantones',
            'url_compra'      => '#prod-plantones',
            'features'        => [
                '&#x2705; Desde $2.00 por plantón',
                '&#x1F333; Especies nativas panameñas',
                '&#x1F4CD; Siembra en territorios vulnerables',
                '&#x1F4CA; Certificado de impacto ambiental',
                '&#x1F91D; Genera empleo en comunidades rurales',
                '&#x1F4C4; Factura electrónica DGI incluida',
            ],
        ],
        [
            'slug'            => 'sigs',
            'nombre'          => 'Servicio Integral de Gestión Social (SIGS)',
            'categoria'       => 'sigs',
            'categoria_label' => '&#x1F4BC; Servicio Profesional',
            'icono'           => '&#x1F4BC;',
            'descripcion_corta' => 'Implementamos programas de impacto social medible para tu organización, empresa o comunidad. Metodología PSP + seguimiento de resultados.',
            'tipo_precio'     => 'desde',
            'opcion_precio'   => 'psp_precio_sigs_base',
            'precio_default'  => '500',
            'unidad'          => '',
            'destacado'       => true,
            'nuevo'           => true,
            'boton_texto'     => '&#x1F4E7; Solicitar SIGS',
            'url_compra'      => '#prod-sigs',
            'features'        => [
                '&#x2705; Diagnóstico social de tu territorio',
                '&#x1F4CB; Plan de acción personalizado',
                '&#x1F4CA; Dashboard de seguimiento de impacto',
                '&#x1F91D; Red de actores y beneficiarios',
                '&#x1F4F0; Informes mensuales de resultados',
                '&#x1F3C6; Certificación de impacto social',
            ],
        ],
    ];
}

function psp_get_sigs_planes() {
    return [
        [
            'slug'          => 'basico',
            'nombre'        => 'Plan Básico',
            'opcion_precio' => 'psp_precio_sigs_basico',
            'precio_default'=> '300',
            'periodo_label' => '/ mes',
            'destacado'     => false,
            'desc'          => 'Para organizaciones pequeñas o comunidades. Hasta 50 beneficiarios.',
        ],
        [
            'slug'          => 'estandar',
            'nombre'        => 'Plan Estándar',
            'opcion_precio' => 'psp_precio_sigs_base',
            'precio_default'=> '500',
            'periodo_label' => '/ mes',
            'destacado'     => true,
            'desc'          => 'Para medianas organizaciones. Hasta 200 beneficiarios + dashboard.',
        ],
        [
            'slug'          => 'empresarial',
            'nombre'        => 'Plan Empresarial',
            'opcion_precio' => 'psp_precio_sigs_emp',
            'precio_default'=> '1200',
            'periodo_label' => '/ mes',
            'destacado'     => false,
            'desc'          => 'Para grandes empresas e instituciones. Beneficiarios ilimitados.',
        ],
        [
            'slug'          => 'personalizado',
            'nombre'        => 'Personalizado',
            'opcion_precio' => '',
            'precio_default'=> '0',
            'periodo_label' => 'Cotizar',
            'destacado'     => false,
            'desc'          => 'Diseñamos el plan exacto para tu caso.',
        ],
    ];
}

function psp_get_sigs_features() {
    return [
        [ 'icono'=>'&#x1F50D;', 'titulo'=>'Diagnóstico Social',         'desc'=>'Análisis completo de la situación de pobreza y vulnerabilidad en tu territorio.' ],
        [ 'icono'=>'&#x1F4CB;', 'titulo'=>'Plan de Acción',             'desc'=>'Diseño de programas concretos de empleo, consumo solidario y financiamiento.' ],
        [ 'icono'=>'&#x1F91D;', 'titulo'=>'Gestión de Beneficiarios',   'desc'=>'Registro, seguimiento y apoyo a hogares, productores y emprendedores.' ],
        [ 'icono'=>'&#x1F4CA;', 'titulo'=>'Dashboard de Impacto',       'desc'=>'Visualiza en tiempo real los empleos generados, familias apoyadas y recursos invertidos.' ],
        [ 'icono'=>'&#x1F4F0;', 'titulo'=>'Informes Periódicos',        'desc'=>'Reportes mensuales con indicadores sociales, avances y recomendaciones.' ],
        [ 'icono'=>'&#x1F3C6;', 'titulo'=>'Certificación Social',       'desc'=>'Certificado oficial de Impacto Social emitido por Iniciativa Panamá Sin Pobreza.' ],
        [ 'icono'=>'&#x1F4B0;', 'titulo'=>'Gestión de Fondos',         'desc'=>'Administración transparente de recursos con facturación electrónica DGI.' ],
        [ 'icono'=>'&#x1F4AC;', 'titulo'=>'Soporte Dedicado',           'desc'=>'Gestor social asignado, WhatsApp directo y reuniones periódicas de seguimiento.' ],
    ];
}
