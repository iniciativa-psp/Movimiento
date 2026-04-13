// Supabase Edge Function: factura-generar (DGI Panamá + PAC)
import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

serve(async (req) => {
  const supabase = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!
  );

  const { pago_id } = await req.json();
  if (!pago_id) return new Response(JSON.stringify({ error: "pago_id requerido" }), { status: 400 });

  const { data: pago } = await supabase.from("pagos")
    .select("*, miembros(nombre, email, celular)")
    .eq("id", pago_id).single();

  if (!pago) return new Response(JSON.stringify({ error: "Pago no encontrado" }), { status: 404 });

  // Obtener o crear cliente ERP
  let cliente_id: string;
  const { data: existente } = await supabase.from("erp_clientes")
    .select("id").eq("miembro_id", pago.miembro_id).single();

  if (existente) {
    cliente_id = existente.id;
  } else {
    const { data: nuevo } = await supabase.from("erp_clientes").insert({
      miembro_id: pago.miembro_id,
      nombre:     pago.miembros?.nombre || "Ciudadano",
      email:      pago.miembros?.email  || "",
      telefono:   pago.miembros?.celular || "",
      tipo:       "persona_natural",
    }).select("id").single();
    cliente_id = nuevo!.id;
  }

  // Número de factura secuencial
  const { count } = await supabase.from("facturas").select("*", { count: "exact", head: true });
  const num_factura = `PSP-${String((count || 0) + 1).padStart(8, "0")}`;

  // Generar XML DGI
  const now     = new Date().toISOString();
  const subtotal = pago.monto;
  const itbms   = 0; // Servicios educativos exentos — verificar con PAC
  const total   = subtotal + itbms;

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<fe:FacturaElectronica xmlns:fe="http://factura.dgi.gob.pa/FE">
  <fe:Encabezado>
    <fe:TipoDocumento>01</fe:TipoDocumento>
    <fe:NumeroFactura>${num_factura}</fe:NumeroFactura>
    <fe:FechaEmision>${now}</fe:FechaEmision>
    <fe:Emisor>
      <fe:RUC>${Deno.env.get("PSP_RUC") || ""}</fe:RUC>
      <fe:DV>${Deno.env.get("PSP_DV") || ""}</fe:DV>
      <fe:RazonSocial>Iniciativa Panamá Sin Pobreza</fe:RazonSocial>
    </fe:Emisor>
    <fe:Receptor>
      <fe:NombreRazonSocial>${pago.miembros?.nombre || "Ciudadano"}</fe:NombreRazonSocial>
    </fe:Receptor>
    <fe:TotalesFactura>
      <fe:TotalFacturacion>${total.toFixed(2)}</fe:TotalFacturacion>
      <fe:TotalITBMS>${itbms.toFixed(2)}</fe:TotalITBMS>
    </fe:TotalesFactura>
  </fe:Encabezado>
  <fe:Items>
    <fe:Item>
      <fe:Descripcion>Membresía ${pago.tipo_membresia || "ciudadana"} - Movimiento Panamá Sin Pobreza</fe:Descripcion>
      <fe:Cantidad>1</fe:Cantidad>
      <fe:PrecioUnitario>${subtotal.toFixed(2)}</fe:PrecioUnitario>
      <fe:PrecioTotal>${subtotal.toFixed(2)}</fe:PrecioTotal>
    </fe:Item>
  </fe:Items>
</fe:FacturaElectronica>`;

  // Guardar factura en DB
  const { data: factura } = await supabase.from("facturas").insert({
    pago_id, cliente_id, numero_factura: num_factura,
    subtotal, itbms, total, xml_content: xml,
    estado: "emitida", concepto: `Membresía ${pago.tipo_membresia}`
  }).select("id").single();

  // Actualizar pago con factura_id
  await supabase.from("pagos").update({ factura_id: factura!.id }).eq("id", pago_id);

  // Enviar al PAC (si está configurado)
  const pac_url   = Deno.env.get("PSP_PAC_URL");
  const pac_token = Deno.env.get("PSP_PAC_TOKEN");

  if (pac_url && pac_token) {
    try {
      const pac_res = await fetch(pac_url, {
        method: "POST",
        headers: { "Authorization": `Bearer ${pac_token}`, "Content-Type": "application/xml" },
        body: xml
      });
      const pac_data = await pac_res.json().catch(() => ({}));
      await supabase.from("facturas")
        .update({ pac_respuesta: pac_data, estado: "enviada_pac" })
        .eq("id", factura!.id);
    } catch (e) {
      console.error("PAC error:", e);
    }
  }

  return new Response(JSON.stringify({ factura_id: factura!.id, numero: num_factura, xml }), {
    headers: { "Content-Type": "application/json" }
  });
});
