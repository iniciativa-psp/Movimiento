// Supabase Edge Function: webhook-pago
import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

serve(async (req) => {
  const supabase = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!
  );

  const payload  = await req.text();
  const provider = req.url.split("/").pop() || "unknown";
  const sig      = req.headers.get("x-signature") || req.headers.get("x-webhook-signature") || "";

  // Log webhook
  await supabase.from("webhooks_logs").insert({
    proveedor: provider, payload, signature: sig,
    ip: req.headers.get("x-forwarded-for") || "", estado: "recibido"
  });

  const data = JSON.parse(payload);
  const referencia = data.orderId || data.reference || data.transactionRef || data.referencia || "";

  if (!referencia) {
    return new Response(JSON.stringify({ error: "Sin referencia" }), { status: 400 });
  }

  const estado_proveedor = (data.status || data.estado || "").toLowerCase();
  const aprobado = ["approved","success","completed","aprobado"].includes(estado_proveedor);
  const nuevo_estado = aprobado ? "completado" : "fallido";

  const { data: pago } = await supabase.from("pagos")
    .update({ estado: nuevo_estado, provider_response: data, transaction_id: data.transactionId || data.id || "" })
    .eq("referencia", referencia).select().single();

  if (aprobado && pago) {
    // Sumar puntos
    await supabase.rpc("sumar_puntos", {
      p_miembro_id: pago.miembro_id,
      p_puntos: Math.round(pago.monto * 100),
      p_tipo: "pago",
      p_descripcion: "Pago de membresía confirmado"
    });

    // Generar factura
    await fetch(`${Deno.env.get("SUPABASE_URL")}/functions/v1/factura-generar`, {
      method: "POST",
      headers: {
        "Authorization": `Bearer ${Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")}`,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ pago_id: pago.id })
    });
  }

  return new Response(JSON.stringify({ ok: true }), {
    headers: { "Content-Type": "application/json" }
  });
});
