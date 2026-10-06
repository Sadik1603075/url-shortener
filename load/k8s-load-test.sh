#!/usr/bin/env bash
set -euo pipefail

# Usage: k8s-load-test.sh <port> <app_host> <rdr_host> <access_code> <threads> <rampup> <duration> <out_dir> <k8s_ns>
LOAD_PORT="${1:-8090}"
LOAD_APP_HOST="${2:-app.linkforge.local}"
LOAD_RDR_HOST="${3:-linkforge.local}"
ACCESS_CODE="${4:-DEV-ACCESS-001}"
THREADS="${5:-50}"
RAMPUP="${6:-10}"
DURATION="${7:-60}"
LOAD_OUT="${8:-out}"
K8S_NS="${9:-linkforge}"

echo ""
echo "=============================================="
echo "  LinkForge HPA Load Test (minikube)"
echo "=============================================="

echo ""
echo "=== PRE-TEST: API pods ==="
kubectl get pods -n "$K8S_NS" -l app.kubernetes.io/name=api

echo ""
echo "=== PRE-TEST: HPA ==="
kubectl get hpa api -n "$K8S_NS"

echo ""
echo "--- Checking port-forward to ingress on :${LOAD_PORT} ---"
if ! curl -s -o /dev/null -w '' --max-time 2 "http://127.0.0.1:${LOAD_PORT}/" 2>/dev/null; then
  echo "Port-forward not running. Starting it..."
  kubectl port-forward -n ingress-nginx svc/ingress-nginx-controller "${LOAD_PORT}:80" --address 127.0.0.1 &>/dev/null &
  PF_PID=$!
  sleep 3
  echo "Port-forward started (PID ${PF_PID})"
else
  echo "Port-forward already running."
  PF_PID=""
fi

echo ""
echo "--- Minting a short code ---"
RESPONSE=$(curl -s -X POST "http://127.0.0.1:${LOAD_PORT}/api/v1/urls" \
  -H "Host: ${LOAD_APP_HOST}" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"access_code\":\"${ACCESS_CODE}\",\"long_url\":\"https://example.com/load-test\"}")
CODE=$(echo "$RESPONSE" | sed -n 's/.*"short_code":"\([^"]*\)".*/\1/p')

if [ -z "$CODE" ]; then
  echo "ERROR: could not mint a short code. Response: $RESPONSE"
  echo "Is the DB seeded and the port-forward up?"
  exit 1
fi
echo "Short code: $CODE"

echo ""
echo "--- Verifying redirect ---"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: ${LOAD_RDR_HOST}" \
  "http://127.0.0.1:${LOAD_PORT}/${CODE}")
echo "GET /${CODE} -> ${HTTP_CODE}"
if [ "$HTTP_CODE" != "302" ]; then
  echo "ERROR: expected 302, got ${HTTP_CODE}"
  exit 1
fi

echo ""
echo "=== STARTING LOAD TEST (${THREADS} threads, ${DURATION}s) ==="
echo "Tip: watch HPA live in another terminal:"
echo "  kubectl get hpa api -n ${K8S_NS} -w"
echo ""

mkdir -p "$LOAD_OUT"
rm -rf "${LOAD_OUT}/redirect.jtl" "${LOAD_OUT}/redirect-report"

jmeter -n -t load/redirect-throughput.jmx \
  -Jhost=127.0.0.1 -Jport="$LOAD_PORT" -Jhost_header="$LOAD_RDR_HOST" -Jcode="$CODE" \
  -Jthreads="$THREADS" -Jrampup="$RAMPUP" -Jduration="$DURATION" \
  -l "${LOAD_OUT}/redirect.jtl" -e -o "${LOAD_OUT}/redirect-report"

echo ""
echo "=== POST-TEST: HPA ==="
kubectl get hpa api -n "$K8S_NS"

echo ""
echo "=== POST-TEST: API pods ==="
kubectl get pods -n "$K8S_NS" -l app.kubernetes.io/name=api

echo ""
echo "HTML report: ${LOAD_OUT}/redirect-report/index.html"

if [ -n "$PF_PID" ]; then
  echo "(Port-forward PID ${PF_PID} still running - kill it when done)"
fi
