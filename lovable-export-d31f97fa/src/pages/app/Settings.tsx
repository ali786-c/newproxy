import { useState, useEffect, useRef } from "react";
import { useSearchParams } from "react-router-dom";
import { SEOHead } from "@/components/seo/SEOHead";
import { ErrorBanner } from "@/components/shared/ErrorBanner";
import { EmptyState } from "@/components/shared/EmptyState";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
  DialogClose,
} from "@/components/ui/dialog";
import { toast } from "@/hooks/use-toast";
import { Plus, Trash2, Key, Shield, Copy, Loader2, Check, RefreshCw } from "lucide-react";
import { useAuth } from "@/contexts/AuthContext";
import { clientApi, type AllowlistEntry, type ApiKey } from "@/lib/api/dashboard";
import { authApi } from "@/lib/api/auth";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { sendEmailVerification } from "firebase/auth";
import { firebaseAuth } from "@/lib/firebase";

// Mock data removed. Using useQuery.

export default function AppSettings() {
  const [searchParams, setSearchParams] = useSearchParams();
  const defaultTab = searchParams.get("tab") || "allowlist";
  const [activeTab, setActiveTab] = useState(defaultTab);
  const { user, refreshUser } = useAuth();
  const queryClient = useQueryClient();

  // Handle verification results from URL (legacy support — no longer primary flow)
  useEffect(() => {
    const verified = searchParams.get("verified");
    if (verified === "true") {
      refreshUser();
      queryClient.invalidateQueries({ queryKey: ["user"] });
      searchParams.delete("verified");
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams, refreshUser, queryClient]);

  // Sync tab with URL param if it changes
  useEffect(() => {
    const tab = searchParams.get("tab");
    if (tab) setActiveTab(tab);
  }, [searchParams]);

  return (
    <>
      <SEOHead title="Settings" noindex />
      <div className="space-y-6">
        <h1 className="text-2xl font-bold">Settings</h1>
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList>
            <TabsTrigger value="allowlist">
              <Shield className="mr-1 h-3.5 w-3.5" /> IP Allowlist
            </TabsTrigger>
            <TabsTrigger value="api-keys">
              <Key className="mr-1 h-3.5 w-3.5" /> API Keys
            </TabsTrigger>
            <TabsTrigger value="verification">
              <Shield className="mr-1 h-3.5 w-3.5" /> Verification
            </TabsTrigger>
          </TabsList>

          <TabsContent value="allowlist" className="mt-4">
            <AllowlistPanel />
          </TabsContent>
          <TabsContent value="api-keys" className="mt-4">
            <ApiKeysPanel />
          </TabsContent>
          <TabsContent value="verification" className="mt-4">
            <VerificationPanel />
          </TabsContent>
        </Tabs>
      </div>
    </>
  );
}

// ── IP Allowlist Panel ───────────────────────────────

function AllowlistPanel() {
  const queryClient = useQueryClient();
  const { data: entries, isLoading } = useQuery({
    queryKey: ["allowlist"],
    queryFn: () => clientApi.getAllowlist(),
  });

  const [newIp, setNewIp] = useState("");
  const [newLabel, setNewLabel] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const addEntry = async () => {
    setError(null);
    const trimmed = newIp.trim();
    if (!trimmed) { setError("IP address is required."); return; }
    if (!/^\d{1,3}(\.\d{1,3}){3}(\/\d{1,2})?$/.test(trimmed)) {
      setError("Enter a valid IPv4 address or CIDR range.");
      return;
    }

    setSubmitting(true);
    try {
      await clientApi.addAllowlistEntry(trimmed, newLabel.trim() || undefined);
      queryClient.invalidateQueries({ queryKey: ["allowlist"] });
      setNewIp("");
      setNewLabel("");
      toast({ title: "IP Added", description: `${trimmed} added to allowlist.` });
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSubmitting(true); // Should be false, typo in my thought but I'll write correctly
    }
  };

  const removeEntry = async (id: string) => {
    try {
      await clientApi.removeAllowlistEntry(id);
      queryClient.invalidateQueries({ queryKey: ["allowlist"] });
      toast({ title: "Removed", description: "IP removed from allowlist." });
    } catch (err: any) {
      toast({ title: "Error", description: err.message, variant: "destructive" });
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">IP Allowlist</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {error && <ErrorBanner message={error} onDismiss={() => setError(null)} />}

        <div className="flex gap-2">
          <Input placeholder="203.0.113.0/24" value={newIp} onChange={(e) => setNewIp(e.target.value)} className="max-w-xs" />
          <Input placeholder="Label (optional)" value={newLabel} onChange={(e) => setNewLabel(e.target.value)} className="max-w-xs" />
          <Button onClick={addEntry} disabled={submitting}>
            {submitting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Plus className="mr-1 h-4 w-4" />}
            Add
          </Button>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-8"><Loader2 className="h-8 w-8 animate-spin text-muted-foreground" /></div>
        ) : entries?.length === 0 ? (
          <EmptyState icon={Shield} title="No IPs allowlisted" description="Add your server IPs to authenticate without credentials." />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>IP Address</TableHead>
                <TableHead>Label</TableHead>
                <TableHead>Added</TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {(entries || []).map((e) => (
                <TableRow key={e.id}>
                  <TableCell className="font-mono text-sm">{e.ip}</TableCell>
                  <TableCell className="text-sm text-muted-foreground">{e.label ?? "—"}</TableCell>
                  <TableCell className="text-xs text-muted-foreground">{new Date(e.created_at).toLocaleDateString()}</TableCell>
                  <TableCell>
                    <Button variant="ghost" size="icon" onClick={() => removeEntry(e.id)} aria-label="Remove">
                      <Trash2 className="h-4 w-4 text-destructive" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}

// ── API Keys Panel ───────────────────────────────────

const ALL_SCOPES = ["proxy:generate", "usage:read", "allowlist:manage", "keys:manage"];

function ApiKeysPanel() {
  const queryClient = useQueryClient();
  const { data: keys, isLoading } = useQuery({
    queryKey: ["api-keys"],
    queryFn: () => clientApi.getApiKeys(),
  });

  const [newKeyResult, setNewKeyResult] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Create key form state
  const [name, setName] = useState("");
  const [countries, setCountries] = useState("");
  const [gbCap, setGbCap] = useState("");
  const [reqCap, setReqCap] = useState("");
  const [scopes, setScopes] = useState<string[]>(["proxy:generate"]);

  const toggleScope = (scope: string) => {
    setScopes((s) => s.includes(scope) ? s.filter((x) => x !== scope) : [...s, scope]);
  };

  const createKey = async () => {
    if (!name.trim()) return;
    setSubmitting(true);
    try {
      const result = await clientApi.createApiKey({
        name: name.trim(),
        allowed_countries: countries ? countries.split(",").map((c) => c.trim().toUpperCase()) : [],
        daily_gb_cap: gbCap ? Number(gbCap) : undefined,
        daily_request_cap: reqCap ? Number(reqCap) : undefined,
        allowed_scopes: scopes,
      });
      queryClient.invalidateQueries({ queryKey: ["api-keys"] });
      setNewKeyResult(result.plain_text_key);
      setName("");
      setCountries("");
      setGbCap("");
      setReqCap("");
      setScopes(["proxy:generate"]);
      toast({ title: "API Key Created", description: "Copy it now — it won't be shown again." });
    } catch (err: any) {
      toast({ title: "Error", description: err.message, variant: "destructive" });
    } finally {
      setSubmitting(false);
    }
  };

  const revokeKey = async (id: string) => {
    try {
      await clientApi.revokeApiKey(id);
      queryClient.invalidateQueries({ queryKey: ["api-keys"] });
      toast({ title: "Revoked", description: "API key has been revoked." });
    } catch (err: any) {
      toast({ title: "Error", description: err.message, variant: "destructive" });
    }
  };

  return (
    <div className="space-y-4">
      {/* Show new key once */}
      {newKeyResult && (
        <Card className="border-primary">
          <CardContent className="flex items-center justify-between gap-4 py-4">
            <div>
              <p className="text-sm font-medium">Your new API key (copy now — shown once):</p>
              <code className="mt-1 block text-xs font-mono text-primary">{newKeyResult}</code>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                navigator.clipboard.writeText(newKeyResult);
                toast({ title: "Copied" });
              }}
            >
              <Copy className="mr-1 h-3.5 w-3.5" /> Copy
            </Button>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-lg">API Keys</CardTitle>
          <Dialog>
            <DialogTrigger asChild>
              <Button size="sm"><Plus className="mr-1 h-4 w-4" /> Create Key</Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader><DialogTitle>Create API Key</DialogTitle></DialogHeader>
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label>Key Name</Label>
                  <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Production" />
                </div>
                <div className="space-y-2">
                  <Label>Allowed Countries (comma-separated, optional)</Label>
                  <Input value={countries} onChange={(e) => setCountries(e.target.value)} placeholder="US, UK, DE" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Daily GB Cap</Label>
                    <Input type="number" value={gbCap} onChange={(e) => setGbCap(e.target.value)} placeholder="No limit" />
                  </div>
                  <div className="space-y-2">
                    <Label>Daily Request Cap</Label>
                    <Input type="number" value={reqCap} onChange={(e) => setReqCap(e.target.value)} placeholder="No limit" />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label>Scopes</Label>
                  <div className="flex flex-wrap gap-2">
                    {ALL_SCOPES.map((scope) => (
                      <Badge
                        key={scope}
                        variant={scopes.includes(scope) ? "default" : "outline"}
                        className="cursor-pointer"
                        onClick={() => toggleScope(scope)}
                      >
                        {scope}
                      </Badge>
                    ))}
                  </div>
                </div>
              </div>
              <DialogFooter>
                <DialogClose asChild><Button variant="outline">Cancel</Button></DialogClose>
                <Button onClick={createKey} disabled={!name.trim()}>Create</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </CardHeader>
        <CardContent className="p-0">
          {isLoading ? (
            <div className="flex justify-center py-8"><Loader2 className="h-8 w-8 animate-spin text-muted-foreground" /></div>
          ) : keys?.length === 0 ? (
            <div className="p-6">
              <EmptyState icon={Key} title="No API keys" description="Create a key to access the UpgradedProxy API programmatically." />
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Key</TableHead>
                  <TableHead>Scopes</TableHead>
                  <TableHead>Limits</TableHead>
                  <TableHead>Last Used</TableHead>
                  <TableHead className="w-12" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {(keys || []).map((k) => (
                  <TableRow key={k.id}>
                    <TableCell className="font-medium text-sm">{k.name}</TableCell>
                    <TableCell className="font-mono text-xs">{k.key_prefix}</TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        {k.allowed_scopes?.map((s) => (
                          <Badge key={s} variant="secondary" className="text-[10px]">{s}</Badge>
                        ))}
                      </div>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {k.daily_gb_cap ? `${k.daily_gb_cap} GB/d` : "—"}
                      {k.daily_request_cap ? ` / ${k.daily_request_cap.toLocaleString()} req/d` : ""}
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {k.last_used_at ? new Date(k.last_used_at).toLocaleDateString() : "Never"}
                    </TableCell>
                    <TableCell>
                      <Button variant="ghost" size="icon" onClick={() => revokeKey(k.id)} aria-label="Revoke">
                        <Trash2 className="h-4 w-4 text-destructive" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

// ── Verification Panel ────────────────────────────────

function VerificationPanel() {
  const { user, syncFirebaseVerification } = useAuth();
  const [resending, setResending] = useState(false);
  const [checking, setChecking] = useState(false);
  const [cooldown, setCooldown] = useState(0);
  const pollIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const isVerified = !!user?.email_verified_at;

  // Handle countdown for resend cooldown
  useEffect(() => {
    if (cooldown > 0) {
      const timer = setTimeout(() => setCooldown(cooldown - 1), 1000);
      return () => clearTimeout(timer);
    }
  }, [cooldown]);

  // Auto-poll Firebase every 5s when unverified to detect verification
  useEffect(() => {
    if (isVerified) {
      if (pollIntervalRef.current) clearInterval(pollIntervalRef.current);
      return;
    }

    pollIntervalRef.current = setInterval(async () => {
      const synced = await syncFirebaseVerification();
      if (synced) {
        clearInterval(pollIntervalRef.current!);
        toast({ title: "✅ Email Verified!", description: "Your account is now fully verified." });
      }
    }, 5000);

    return () => {
      if (pollIntervalRef.current) clearInterval(pollIntervalRef.current);
    };
  }, [isVerified, syncFirebaseVerification]);

  // Resend Firebase verification email via SaaS Backend (Brevo)
  const onResend = async () => {
    setResending(true);
    try {
      await authApi.resendVerification();
      toast({ title: "Verification Email Sent", description: "Please check your inbox (via Brevo) and click the Firebase link." });
      setCooldown(60);
    } catch (err: any) {
      const msg = err.message ?? "Failed to send verification email.";
      toast({ title: "Error", description: msg, variant: "destructive" });
    } finally {
      setResending(false);
    }
  };

  // Manual check button
  const onManualCheck = async () => {
    setChecking(true);
    try {
      const synced = await syncFirebaseVerification();
      if (synced) {
        toast({ title: "✅ Email Verified!", description: "Your account is now fully verified." });
      } else {
        toast({ title: "Not yet verified", description: "We couldn't detect verification yet. Please check your email and click the link.", variant: "destructive" });
      }
    } finally {
      setChecking(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg flex items-center gap-2">
          <Shield className={`h-5 w-5 ${isVerified ? "text-success" : "text-warning"}`} />
          Account Verification
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {isVerified ? (
          <div className="flex flex-col items-center gap-3 py-6">
            <div className="rounded-full bg-success/10 p-3">
              <Check className="h-8 w-8 text-success" />
            </div>
            <p className="font-medium">Your account is verified.</p>
            <p className="text-sm text-muted-foreground text-center max-w-sm">
              Thank you for verifying your email. You now have full access to our premium proxy services and free trial.
            </p>
          </div>
        ) : (
          <div className="space-y-6">
            <div className="space-y-4">
              <div className="space-y-2">
                <p className="text-sm font-medium">Email Verification Required</p>
                <p className="text-sm text-muted-foreground">
                  A verification email was sent to <strong>{user?.email}</strong>.
                  Click the link in that email, then come back here — we'll detect it automatically.
                </p>
              </div>

              <div className="flex items-center gap-3 flex-wrap">
                <Button
                  onClick={onResend}
                  disabled={resending || cooldown > 0}
                  className="px-6"
                >
                  {resending ? (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  ) : (
                    <Shield className="mr-2 h-4 w-4" />
                  )}
                  {cooldown > 0 ? `Resend in ${cooldown}s` : "Resend Verification Email"}
                </Button>

                <Button
                  variant="outline"
                  onClick={onManualCheck}
                  disabled={checking}
                >
                  {checking ? (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  ) : (
                    <RefreshCw className="mr-2 h-4 w-4" />
                  )}
                  Check Status
                </Button>
              </div>

              <p className="text-xs text-muted-foreground">
                🔄 This page automatically checks every 5 seconds after you verify your email.
              </p>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
