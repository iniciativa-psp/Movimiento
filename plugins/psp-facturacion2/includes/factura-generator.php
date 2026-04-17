<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Escapa caracteres especiales para XML
 */
function psp_esc_xml( $s ) {
    return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * Genera el XML DGI Panamá para una factura
 *
 * @param array $factura  ['numero_factura', 'subtotal', 'itbms', 'total']
 * @param array $cliente  ['nombre', 'ruc', 'email']
 * @param array $items    [['descripcion', 'cantidad', 'precio'], ...]
 * @return string XML completo
 */
function psp_generar_xml_factura( $factura, $cliente, $items ) {
    $ruc        = get_option( 'psp_ruc',          '' );
    $dv         = get_option( 'psp_dv',           '' );
    $razon      = get_option( 'psp_razon_social',  'Iniciativa Panamá Sin Pobreza' );
    $itbms_rate = (float) get_option( 'psp_itbms', '0' );

    $num      = $factura['numero_factura'];
    $subtotal = (float) $factura['subtotal'];
    $itbms    = round( $subtotal * $itbms_rate / 100, 2 );
    $total    = $subtotal + $itbms;
    $now      = date( 'c' );

    $items_xml = '';
    foreach ( $items as $item ) {
        $precio   = (float) ( $item['precio']   ?? $subtotal );
        $cantidad = (int)   ( $item['cantidad'] ?? 1 );
        $items_xml .= '
    <fe:Item>
      <fe:Descripcion>'  . psp_esc_xml( $item['descripcion'] ?? 'Membresía' ) . '</fe:Descripcion>
      <fe:Cantidad>'     . $cantidad . '</fe:Cantidad>
      <fe:PrecioUnitario>' . number_format( $precio, 2, '.', '' ) . '</fe:PrecioUnitario>
      <fe:PrecioTotal>'  . number_format( $precio * $cantidad, 2, '.', '' ) . '</fe:PrecioTotal>
      <fe:TasaITBMS>'    . $itbms_rate . '</fe:TasaITBMS>
      <fe:MontoITBMS>'   . number_format( $precio * $cantidad * $itbms_rate / 100, 2, '.', '' ) . '</fe:MontoITBMS>
    </fe:Item>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>
<fe:FacturaElectronica
  xmlns:fe="http://factura.dgi.gob.pa/FE"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <fe:Encabezado>
    <fe:TipoDocumento>01</fe:TipoDocumento>
    <fe:NumeroFactura>'  . psp_esc_xml( $num ) . '</fe:NumeroFactura>
    <fe:FechaEmision>'   . $now . '</fe:FechaEmision>
    <fe:NaturaOperacion>01</fe:NaturaOperacion>
    <fe:TipoEmision>01</fe:TipoEmision>
    <fe:Emisor>
      <fe:RUC>'         . psp_esc_xml( $ruc )   . '</fe:RUC>
      <fe:DV>'          . psp_esc_xml( $dv )    . '</fe:DV>
      <fe:RazonSocial>' . psp_esc_xml( $razon ) . '</fe:RazonSocial>
      <fe:DireccionFiscal>Ciudad de Panamá, Panamá</fe:DireccionFiscal>
    </fe:Emisor>
    <fe:Receptor>
      <fe:NombreRazonSocial>' . psp_esc_xml( $cliente['nombre'] ?? 'Ciudadano' ) . '</fe:NombreRazonSocial>
      <fe:RUC>'               . psp_esc_xml( $cliente['ruc']    ?? '' )          . '</fe:RUC>
      <fe:Correo>'            . psp_esc_xml( $cliente['email']  ?? '' )          . '</fe:Correo>
    </fe:Receptor>
    <fe:TotalesFactura>
      <fe:TotalFacturacion>' . number_format( $total,    2, '.', '' ) . '</fe:TotalFacturacion>
      <fe:TotalITBMS>'       . number_format( $itbms,    2, '.', '' ) . '</fe:TotalITBMS>
      <fe:SubTotal>'         . number_format( $subtotal, 2, '.', '' ) . '</fe:SubTotal>
    </fe:TotalesFactura>
  </fe:Encabezado>
  <fe:Items>' . $items_xml . '
  </fe:Items>
</fe:FacturaElectronica>';
}

/**
 * Envía el XML al PAC configurado
 */
function psp_enviar_al_pac( $xml, $factura_id ) {
    $pac_url   = get_option( 'psp_pac_url',   '' );
    $pac_token = get_option( 'psp_pac_token', '' );

    if ( ! $pac_url || ! $pac_token ) {
        return [ 'success' => false, 'error' => 'PAC no configurado' ];
    }

    $response = wp_remote_post( $pac_url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $pac_token,
            'Content-Type'  => 'application/xml',
            'X-Factura-ID'  => $factura_id,
        ],
        'body' => $xml,
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'error' => $response->get_error_message() ];
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code >= 200 && $code < 300 ) {
        return [ 'success' => true, 'cufe' => $body['cufe'] ?? '', 'data' => $body ];
    }

    return [ 'success' => false, 'error' => "HTTP $code", 'data' => $body ];
}

/**
 * Proceso completo: generar XML → guardar → enviar PAC → asiento contable
 */
function psp_procesar_factura_completa( $pago_id ) {
    if ( ! class_exists( 'PSP_Supabase' ) ) {
        return null;
    }

    // 1. Obtener pago con datos del miembro
    $pagos = PSP_Supabase::select( 'pagos', [
        'id'     => 'eq.' . $pago_id,
        'select' => '*,miembros(nombre,email,celular)',
        'limit'  => 1,
    ] );
    if ( ! $pagos ) return null;
    $pago = $pagos[0];

    // 2. Obtener o crear cliente ERP
    $clientes = PSP_Supabase::select( 'erp_clientes', [
        'miembro_id' => 'eq.' . $pago['miembro_id'],
        'limit'      => 1,
    ] );

    if ( $clientes ) {
        $cliente_data = $clientes[0];
    } else {
        $nuevo = PSP_Supabase::insert( 'erp_clientes', [
            'miembro_id' => $pago['miembro_id'],
            'nombre'     => $pago['miembros']['nombre']  ?? 'Ciudadano',
            'email'      => $pago['miembros']['email']   ?? '',
            'telefono'   => $pago['miembros']['celular'] ?? '',
            'tipo'       => 'persona_natural',
            'tenant_id'  => get_option( 'psp_tenant_id', 'panama' ),
        ], true );
        $cliente_data = $nuevo ? $nuevo[0] : [];
    }

    // 3. Número de factura secuencial
    $existing    = PSP_Supabase::select( 'facturas', [ 'select' => 'id', 'limit' => 9999 ] );
    $num_factura = 'PSP-' . str_pad( count( $existing ?? [] ) + 1, 8, '0', STR_PAD_LEFT );

    // 4. Calcular montos
    $subtotal = (float) $pago['monto'];
    $itbms    = round( $subtotal * (float) get_option( 'psp_itbms', '0' ) / 100, 2 );
    $total    = $subtotal + $itbms;

    $items = [ [
        'descripcion' => 'Membresía ' . ucfirst( $pago['tipo_membresia'] ?? 'ciudadana' ) . ' — Movimiento Panamá Sin Pobreza',
        'cantidad'    => 1,
        'precio'      => $subtotal,
    ] ];

    $factura_data = [
        'numero_factura' => $num_factura,
        'subtotal'       => $subtotal,
        'itbms'          => $itbms,
        'total'          => $total,
    ];

    // 5. Generar XML
    $xml = psp_generar_xml_factura( $factura_data, $cliente_data, $items );

    // 6. Guardar en Supabase
    $factura_row = PSP_Supabase::insert( 'facturas', [
        'pago_id'        => $pago_id,
        'cliente_id'     => $cliente_data['id'] ?? null,
        'numero_factura' => $num_factura,
        'subtotal'       => $subtotal,
        'itbms'          => $itbms,
        'total'          => $total,
        'xml_content'    => $xml,
        'estado'         => 'emitida',
        'concepto'       => $items[0]['descripcion'],
        'tenant_id'      => get_option( 'psp_tenant_id', 'panama' ),
    ], true );

    if ( ! $factura_row ) return null;
    $factura_id = $factura_row[0]['id'];

    // 7. Actualizar pago con factura_id
    PSP_Supabase::update( 'pagos', [ 'factura_id' => $factura_id ], [ 'id' => 'eq.' . $pago_id ] );

    // 8. Enviar al PAC
    $pac_result = psp_enviar_al_pac( $xml, $factura_id );
    if ( $pac_result['success'] ) {
        PSP_Supabase::update( 'facturas', [
            'estado'        => 'enviada_pac',
            'pac_respuesta' => wp_json_encode( $pac_result['data'] ),
            'dgi_cufe'      => $pac_result['cufe'] ?? '',
        ], [ 'id' => 'eq.' . $factura_id ] );
    }

    // 9. Asiento contable
    psp_registrar_asiento_contable( $pago_id, $factura_id, $subtotal, $itbms, $total );

    return [ 'factura_id' => $factura_id, 'numero' => $num_factura, 'xml' => $xml ];
}

/**
 * Registra asientos en el libro diario
 */
function psp_registrar_asiento_contable( $pago_id, $factura_id, $subtotal, $itbms, $total ) {
    if ( ! class_exists( 'PSP_Supabase' ) ) return;

    $trans = PSP_Supabase::insert( 'erp_transacciones', [
        'tipo'           => 'ingreso',
        'categoria'      => 'membresia',
        'descripcion'    => 'Membresía PSP cobrada',
        'monto'          => $total,
        'pago_id'        => $pago_id,
        'factura_id'     => $factura_id,
        'cuenta_debito'  => '1001',
        'cuenta_credito' => '4001',
        'fecha'          => date( 'Y-m-d' ),
        'tenant_id'      => get_option( 'psp_tenant_id', 'panama' ),
    ], true );

    if ( ! $trans ) return;
    $trans_id = $trans[0]['id'];

    // Debe: Caja
    PSP_Supabase::insert( 'erp_libro_diario', [
        'transaccion_id' => $trans_id,
        'fecha'          => date( 'Y-m-d' ),
        'descripcion'    => 'Membresía PSP cobrada',
        'cuenta'         => '1001 — Caja',
        'debe'           => $total,
        'haber'          => 0,
        'tenant_id'      => get_option( 'psp_tenant_id', 'panama' ),
    ], true );

    // Haber: Ingresos
    PSP_Supabase::insert( 'erp_libro_diario', [
        'transaccion_id' => $trans_id,
        'fecha'          => date( 'Y-m-d' ),
        'descripcion'    => 'Membresía PSP — ingreso',
        'cuenta'         => '4001 — Ingresos por Membresías',
        'debe'           => 0,
        'haber'          => $subtotal,
        'tenant_id'      => get_option( 'psp_tenant_id', 'panama' ),
    ], true );

    if ( $itbms > 0 ) {
        PSP_Supabase::insert( 'erp_libro_diario', [
            'transaccion_id' => $trans_id,
            'fecha'          => date( 'Y-m-d' ),
            'descripcion'    => 'ITBMS por pagar',
            'cuenta'         => '2101 — ITBMS por Pagar',
            'debe'           => 0,
            'haber'          => $itbms,
            'tenant_id'      => get_option( 'psp_tenant_id', 'panama' ),
        ], true );
    }
}
