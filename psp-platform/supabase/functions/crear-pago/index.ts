// Supabase Edge Function: crear-pago
import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });

  try {
    const supabase = createClient(
      Deno.env.get("SUPABASE_URL")!,
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!
    );

    const body = await req.json();
    const { miembro_id, monto, metodo, tipo_membresia } = body;

    if (!miembro_id || !monto || !metodo) {
      return new Response(JSON.stringify({ error: "Datos incompletos" }), {
        status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" }
      });
    }

    const referencia = "PSP-" + crypto.randomUUID().replace(/-/g,"").substring(0,10).toUpperCase();

    const { data: pago, error } = await supabase.from("pagos").insert({
      miembro_id, monto, metodo, tipo_membresia,
      estado: "pendiente", referencia, tenant_id: "panama",
    }).select().single();

    if (error) throw error;

    return new Response(JSON.stringify({ pago_id: pago.id, referencia, monto }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" }
    });

  } catch (err) {
    return new Response(JSON.stringify({ error: err.message }), {
      status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" }
    });
  }
});
