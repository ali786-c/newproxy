import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, MessageSchema } from "@/lib/api/client";
import { z } from "zod";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { toast } from "@/hooks/use-toast";
import { Globe, Mail, Shield, Save } from "lucide-react";
import { useState, useEffect } from "react";

export default function AdminSettings() {
  const queryClient = useQueryClient();
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
      toast({ title: "Settings Saved", description: "System configuration updated successfully." });
    },
    onError: (e: any) => toast({ title: "Error", description: e.message, variant: "destructive" }),
  });

  const updateField = (key: string, value: any) => {
    setForm(prev => ({ ...prev, [key]: value }));
  };

  const handleSave = () => {
    // Only send fields that are actually displayed on this page
    const allowedKeys = [
      'site_name',
      'support_email',
      'admin_notification_email',
      'maintenance_mode',
      'smtp_host',
      'smtp_port',
      'smtp_user',
      'smtp_pass',
      'admin_2fa_required',
      'rate_limiting_enabled'
    ];

    const dataToSave: Record<string, any> = {};
    allowedKeys.forEach(key => {
      if (form[key] !== undefined) {
        dataToSave[key] = form[key];
      }
    });

    mutation.mutate(dataToSave);
  };

  if (isLoading) return <div className="p-8 text-center text-muted-foreground">Loading settings...</div>;

  return (
    <>
      <SEOHead title="Admin — Settings" noindex />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">System Settings</h1>
          <Button onClick={handleSave} disabled={mutation.isPending}>
            <Save className="mr-2 h-4 w-4" />
            {mutation.isPending ? "Saving..." : "Save All Changes"}
          </Button>
        </div>

        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2 text-lg"><Globe className="h-5 w-5" /> General</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <Label>Site Name</Label>
              <Input
                value={form.site_name || ""}
                onChange={(e) => updateField("site_name", e.target.value)}
                placeholder="UpgradedProxy"
              />
            </div>
            <div className="space-y-1.5">
              <Label>Support Email</Label>
              <Input
                value={form.support_email || ""}
                onChange={(e) => updateField("support_email", e.target.value)}
                placeholder="support@upgradedproxy.com"
              />
            </div>
            <div className="space-y-1.5">
              <Label>Admin Notification Email (for alerts)</Label>
              <Input
                value={form.admin_notification_email || ""}
                onChange={(e) => updateField("admin_notification_email", e.target.value)}
                placeholder="aliyantarar4@gmail.com"
              />
            </div>
            <div className="flex items-center justify-between pt-2">
              <div>
                <p className="text-sm font-medium">Maintenance Mode</p>
                <p className="text-xs text-muted-foreground">Disable public access temporarily</p>
              </div>
              <Switch
                checked={form.maintenance_mode === "1" || form.maintenance_mode === true}
                onCheckedChange={(checked) => updateField("maintenance_mode", checked)}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2 text-lg"><Mail className="h-5 w-5" /> Email / SMTP</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>SMTP Host</Label>
                <Input value={form.smtp_host || ""} onChange={(e) => updateField("smtp_host", e.target.value)} placeholder="smtp.example.com" />
              </div>
              <div className="space-y-1.5">
                <Label>SMTP Port</Label>
                <Input value={form.smtp_port || ""} onChange={(e) => updateField("smtp_port", e.target.value)} placeholder="587" />
              </div>
              <div className="space-y-1.5">
                <Label>Username</Label>
                <Input value={form.smtp_user || ""} onChange={(e) => updateField("smtp_user", e.target.value)} placeholder="user@example.com" />
              </div>
              <div className="space-y-1.5">
                <Label>Password</Label>
                <Input type="password" value={form.smtp_pass || ""} onChange={(e) => updateField("smtp_pass", e.target.value)} placeholder="••••••••" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2 text-lg"><Shield className="h-5 w-5" /> Security</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">2FA Required for Admin</p>
                <p className="text-xs text-muted-foreground">Force all admins to use two-factor authentication</p>
              </div>
              <Switch
                checked={form.admin_2fa_required === "1" || form.admin_2fa_required === true}
                onCheckedChange={(v) => updateField("admin_2fa_required", v)}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Rate Limiting</p>
                <p className="text-xs text-muted-foreground">Limit API requests per minute to prevent abuse</p>
              </div>
              <Switch
                checked={form.rate_limiting_enabled === "1" || form.rate_limiting_enabled === true}
                onCheckedChange={(v) => updateField("rate_limiting_enabled", v)}
              />
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
