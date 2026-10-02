# Kubernetes manifests (Kustomize)

Local-first deployment for LinkForge (Phase 1.5, [ADR-0005](../../docs/adr/0005-local-kubernetes-topology.md)).
These base manifests are also the intended deploy base for the AWS/EKS phase.

```
infra/k8s/
  base/                 # environment-agnostic manifests (the source of truth)
    namespace.yaml
    kustomization.yaml
  overlays/
    local/              # minikube overlay — image tags, replicas, env labels
      kustomization.yaml
```

## Render / apply

```bash
# Render (no cluster needed) — review the full output:
kubectl kustomize infra/k8s/overlays/local

# Apply to the current context (minikube):
kubectl apply -k infra/k8s/overlays/local
```

Later tickets add resources to `base/kustomization.yaml` and uncomment the image/replica
knobs in the overlay. Day-to-day this is driven by the `make k8s-*` targets (K8S-13).

## Conventions

- **Base is environment-agnostic**; anything environment-specific (tags, replica counts,
  hosts, resource sizing) lives in an overlay.
- Org labels (`part-of`, `managed-by`) are applied without touching selectors, so they're
  safe to change; each workload owns its own selector labels.
- Namespace: `linkforge` (set once in the base).
