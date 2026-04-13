#!/bin/bash
# Deploy Supabase Edge Functions
set -e
command -v supabase >/dev/null 2>&1 || { echo "Instala Supabase CLI: npm i -g supabase"; exit 1; }

PROJ=${SUPABASE_PROJECT_ID:-"tu-project-id"}
DIR="$(dirname "$0")/../supabase/functions"

echo "🚀 Deploying Edge Functions..."
supabase functions deploy crear-pago      --project-ref $PROJ
supabase functions deploy webhook-pago    --project-ref $PROJ
supabase functions deploy factura-generar --project-ref $PROJ
supabase functions deploy ranking-update  --project-ref $PROJ
echo "✅ Functions deployed!"
echo ""
echo "Configura secrets:"
echo "  supabase secrets set PSP_RUC=TU_RUC --project-ref $PROJ"
echo "  supabase secrets set PSP_DV=TU_DV --project-ref $PROJ"
echo "  supabase secrets set PSP_PAC_URL=URL_PAC --project-ref $PROJ"
echo "  supabase secrets set PSP_PAC_TOKEN=TOKEN_PAC --project-ref $PROJ"
