// Supabase Edge Function: ranking-update (cron cada hora)
import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

serve(async (_req) => {
  const supabase = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!
  );

  const hoy = new Date().toISOString().split("T")[0];

  // Ranking provincias
  const { data: provincias } = await supabase.rpc("get_ranking", { p_tipo: "provincia", p_limit: 50 });
  if (provincias) {
    await supabase.from("ranking").delete().match({ tipo: "provincia", fecha: hoy });
    await supabase.from("ranking").insert(
      provincias.map((p: any, i: number) => ({
        tipo: "provincia", entidad_id: p.nombre, nombre: p.nombre,
        total: p.total, monto_total: p.monto_total, posicion: i + 1, fecha: hoy
      }))
    );
  }

  // Ranking países
  const { data: paises } = await supabase.rpc("get_ranking", { p_tipo: "pais", p_limit: 50 });
  if (paises) {
    await supabase.from("ranking").delete().match({ tipo: "pais", fecha: hoy });
    await supabase.from("ranking").insert(
      paises.map((p: any, i: number) => ({
        tipo: "pais", entidad_id: p.nombre, nombre: p.nombre,
        total: p.total, monto_total: p.monto_total, posicion: i + 1, fecha: hoy
      }))
    );
  }

  return new Response(JSON.stringify({ ok: true, fecha: hoy }), {
    headers: { "Content-Type": "application/json" }
  });
});
