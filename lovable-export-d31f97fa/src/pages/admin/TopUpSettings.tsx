import { useState, useEffect } from "react";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { toast } from "@/hooks/use-toast";
import { usePaymentConfig } from "@/contexts/PaymentConfigContext";
import { RefreshCw, DollarSign, CreditCard, Wallet, Bitcoin, Check, Save } from "lucide-react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, MessageSchema } from "@/lib/api/client";
import { z } from "zod";

const SOURCE_PROVIDERS = [
  { id: "stripe", name: "Stripe", icon: CreditCard, desc: "Charge client's saved card via Stripe" },
  { id: "crypto", name: "Crypto Wallet", icon: Bitcoin, desc: "Deduct from client's crypto deposit" },
] as const;

export default function TopUpSettings() {
  const queryClient = useQueryClient();
  const { gateways } = usePaymentConfig();
  const [form, setForm] = useState<Record<string, any>>({});

  const { data: settings = {}, isLoading } = useQuery({
    queryKey: ["admin-settings"],
    queryFn: () => api.get("/admin/settings", z.record(z.any())),
  });

  useEffect(() => {
    if (settings) {
      setForm(settings);
    }
  }, [settings]);

  const mutation = useMutation({
    mutationFn: (data: any) => api.post("/admin/settings", MessageSchema, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin-settings"] });
      toast({ title: "Settings Saved", description: "Top-up configuration updated successfully." });
    },
    onError: (e: any) => toast({ title: "Error", description: e.message, variant: "destructive" }),
  });

  const updateField = (key: string, value: any) => {
    setForm(prev => ({ ...prev, [key]: value }));
  };

  const handleSave = () => {
    mutation.mutate(form);
  };

  if (isLoading) return <div className="p-8 text-center text-muted-foreground">Loading configurations...</div>;

  const autoTopUp = form.auto_topup_enabled === "1" || form.auto_topup_enabled === true;

  return (
    <>
      <SEOHead title="Top-Up Settings" noindex />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Top-Up & Balance Settings</h1>
            <p className="text-sm text-muted-foreground">Configure automatic balance top-up for client accounts.</p>
          </div>
          <Button onClick={handleSave} disabled={mutation.isPending}>
            <Save className="mr-2 h-4 w-4" />
            {mutation.isPending ? "Saving..." : "Save All Settings"}
          </Button>
        </div>

        {/* Master Toggle */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-lg">
              <RefreshCw className="h-5 w-5" /> Client Auto Top-Up
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Enable Auto Top-Up for Clients</p>
                <p className="text-xs text-muted-foreground">Allow clients to opt-in to automatic balance replenishment</p>
              </div>
              <Switch
                checked={autoTopUp}
                onCheckedChange={(v) => updateField("auto_topup_enabled", v)}
              />
            </div>
            {autoTopUp && (
              <div className="grid gap-4 sm:grid-cols-3">
                <div className="space-y-1.5">
                  <Label>Min Balance Threshold ($)</Label>
                  <Input
                    type="number"
                    value={form.min_balance_threshold || "5"}
                    onChange={(e) => updateField("min_balance_threshold", e.target.value)}
                  />
                  <p className="text-[11px] text-muted-foreground">Top-up triggers when balance falls below this</p>
                </div>
                <div className="space-y-1.5">
                  <Label>Default Top-Up Amount ($)</Label>
                  <Input
                    type="number"
                    value={form.default_topup_amount || "50"}
                    onChange={(e) => updateField("default_topup_amount", e.target.value)}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>Max Monthly Top-Up ($)</Label>
                  <Input
                    type="number"
                    value={form.max_monthly_topup || "500"}
                    onChange={(e) => updateField("max_monthly_topup", e.target.value)}
                  />
                  <p className="text-[11px] text-muted-foreground">Safety cap per client per month</p>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Source Provider Selection */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-lg">
              <DollarSign className="h-5 w-5" /> Auto Top-Up Source Provider
            </CardTitle>
            <CardDescription>Choose which payment method to charge clients from when auto top-up triggers.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-3">
              {SOURCE_PROVIDERS.map((sp) => {
                const isEnabled = gateways[sp.id as keyof typeof gateways];
                const isSelected = form.topup_source_primary === sp.id;
                return (
                  <button
                    key={sp.id}
                    onClick={() => isEnabled && updateField("topup_source_primary", sp.id)}
                    disabled={!isEnabled}
                    className={`relative flex flex-col items-center gap-2 rounded-lg border-2 p-4 transition-all text-center ${isSelected
                      ? "border-primary bg-primary/5"
                      : isEnabled
                        ? "border-border hover:border-primary/50 cursor-pointer"
                        : "border-border opacity-40 cursor-not-allowed"
                      }`}
                  >
                    {isSelected && (
                      <div className="absolute top-2 right-2 rounded-full bg-primary p-0.5">
                        <Check className="h-3 w-3 text-primary-foreground" />
                      </div>
                    )}
                    <sp.icon className={`h-6 w-6 ${isSelected ? "text-primary" : "text-muted-foreground"}`} />
                    <span className="font-semibold text-sm">{sp.name}</span>
                    <span className="text-[11px] text-muted-foreground">{sp.desc}</span>
                    {!isEnabled && <Badge variant="secondary" className="text-[10px]">Gateway disabled</Badge>}
                    {isSelected && <Badge className="text-[10px]">Primary</Badge>}
                  </button>
                );
              })}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Fallback Source</Label>
                <Select
                  value={form.topup_source_fallback || "none"}
                  onValueChange={(v) => updateField("topup_source_fallback", v)}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">No fallback</SelectItem>
                    {SOURCE_PROVIDERS.filter((sp) => sp.id !== form.topup_source_primary && gateways[sp.id as keyof typeof gateways]).map((sp) => (
                      <SelectItem key={sp.id} value={sp.id}>{sp.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-[11px] text-muted-foreground">Used if primary source fails</p>
              </div>
              <div className="space-y-1.5">
                <Label>Retry Attempts</Label>
                <div className="flex gap-2">
                  <Input
                    type="number"
                    value={form.retry_attempts || "3"}
                    onChange={(e) => updateField("retry_attempts", e.target.value)}
                    className="w-20"
                  />
                  <span className="text-sm text-muted-foreground self-center">attempts, every</span>
                  <Input
                    type="number"
                    value={form.retry_interval || "60"}
                    onChange={(e) => updateField("retry_interval", e.target.value)}
                    className="w-20"
                  />
                  <span className="text-sm text-muted-foreground self-center">min</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Notifications */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Top-Up Notifications</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center justify-between rounded-md border p-3">
              <div>
                <p className="text-sm font-medium">Notify client on successful charge</p>
                <p className="text-xs text-muted-foreground">Send email when auto top-up completes</p>
              </div>
              <Switch
                checked={form.notify_client_success === "1" || form.notify_client_success === true}
                onCheckedChange={(v) => updateField("notify_client_success", v)}
              />
            </div>
            <div className="flex items-center justify-between rounded-md border p-3">
              <div>
                <p className="text-sm font-medium">Notify admin on charge failure</p>
                <p className="text-xs text-muted-foreground">Alert when auto top-up fails after all retries</p>
              </div>
              <Switch
                checked={form.notify_admin_failure === "1" || form.notify_admin_failure === true}
                onCheckedChange={(v) => updateField("notify_admin_failure", v)}
              />
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
