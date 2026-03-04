import { useState } from "react";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "@/hooks/use-toast";
import { Shield, ShieldCheck, Smartphone, Copy, Eye, EyeOff, Loader2, Key, Lock, Plus, Trash2 } from "lucide-react";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/components/ui/input-otp";
import { useUpdateProfile, use2FASetup, use2FAConfirm, use2FADisable, use2FARecoveryCodes } from "@/hooks/use-backend";
import { useAuth } from "@/contexts/AuthContext";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { clientApi } from "@/lib/api/dashboard";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ErrorBanner } from "@/components/shared/ErrorBanner";
import { EmptyState } from "@/components/shared/EmptyState";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
  DialogClose,
} from "@/components/ui/dialog";

export default function Security() {
  const { user } = useAuth();
  const [step, setStep] = useState<"idle" | "setup" | "verify">("idle");
  const [otpCode, setOtpCode] = useState("");
  const [showBackup, setShowBackup] = useState(false);
  const [confirmedRecoveryCodes, setConfirmedRecoveryCodes] = useState<string[]>([]);
  const [disablePassword, setDisablePassword] = useState("");
  const [isDisabling, setIsDisabling] = useState(false);

  // Hooks
  const { data: setupData, isLoading: isLoadingSetup, refetch: refetchSetup } = use2FASetup();
  const confirmMutation = use2FAConfirm();
  const disableMutation = use2FADisable();

  const is2FAEnabled = !!user?.is_2fa_enabled;
  const { data: recoveryCodesData } = use2FARecoveryCodes(is2FAEnabled);

  const updateProfile = useUpdateProfile();
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [updating, setUpdating] = useState(false);

  const handlePasswordChange = async () => {
    if (!newPassword || newPassword !== confirmPassword) {
      toast({ title: "Validation Error", description: "Passwords do not match.", variant: "destructive" });
      return;
    }
    setUpdating(true);
    try {
      await updateProfile.mutateAsync({ password: newPassword, password_confirmation: confirmPassword });
      toast({ title: "Password updated successfully" });
      setNewPassword("");
      setConfirmPassword("");
    } catch (err: any) {
      toast({ title: "Error", description: err.message, variant: "destructive" });
    } finally {
      setUpdating(false);
    }
  };

  const handleStartSetup = () => {
    refetchSetup();
    setStep("setup");
  };

  const handleVerify = async () => {
    if (otpCode.length !== 6) {
      toast({ title: "Invalid code", description: "Enter a 6-digit code", variant: "destructive" });
      return;
    }

    try {
      const result = await confirmMutation.mutateAsync(otpCode);
      setConfirmedRecoveryCodes(result.recovery_codes);
      setStep("idle");
      setOtpCode("");
      toast({ title: "2FA Enabled", description: "Your account is now protected." });
    } catch (err: any) {
      toast({ title: "Verification failed", description: err.message, variant: "destructive" });
    }
  };

  const handleDisable = async () => {
    if (!disablePassword) {
      toast({ title: "Password required", description: "Please enter your password to disable 2FA.", variant: "destructive" });
      return;
    }

    try {
      await disableMutation.mutateAsync({ password: disablePassword });
      toast({ title: "2FA Disabled", description: "Two-factor authentication has been turned off." });
      setDisablePassword("");
      setIsDisabling(false);
    } catch (err: any) {
      toast({ title: "Error", description: err.message, variant: "destructive" });
    }
  };

  const backupCodes = confirmedRecoveryCodes.length > 0
    ? confirmedRecoveryCodes
    : (recoveryCodesData?.recovery_codes || []);

  return (
    <>
      <SEOHead title="Security & 2FA" noindex />
      <div className="space-y-6">
        <h1 className="text-2xl font-bold">Security Management</h1>

        <Tabs defaultValue="account">
          <TabsList>
            <TabsTrigger value="account">
              <Lock className="mr-1 h-3.5 w-3.5" /> Account Security
            </TabsTrigger>
            <TabsTrigger value="allowlist">
              <Shield className="mr-1 h-3.5 w-3.5" /> IP Allowlist
            </TabsTrigger>
            <TabsTrigger value="api-keys">
              <Key className="mr-1 h-3.5 w-3.5" /> API Keys
            </TabsTrigger>
          </TabsList>

          <TabsContent value="account" className="space-y-6 mt-4">
            <Card>
              <CardHeader>
                <div className="flex items-center gap-3">
                  {is2FAEnabled ? (
                    <ShieldCheck className="h-8 w-8 text-success" />
                  ) : (
                    <Shield className="h-8 w-8 text-muted-foreground" />
                  )}
                  <div>
                    <CardTitle className="text-lg">Two-Factor Authentication (TOTP)</CardTitle>
                    <CardDescription>
                      Add an extra layer of security using an authenticator app like Google Authenticator or Authy.
                    </CardDescription>
                  </div>
                  <Badge variant={is2FAEnabled ? "default" : "secondary"} className="ml-auto">
                    {is2FAEnabled ? "Enabled" : "Disabled"}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                {is2FAEnabled && step === "idle" && (
                  <div className="flex flex-col gap-4">
                    <p className="text-sm text-green-600 font-medium">
                      Two-factor authentication is active on your account.
                    </p>
                    <div className="flex gap-2">
                      <Button variant="outline" size="sm" onClick={() => setShowBackup(!showBackup)}>
                        {showBackup ? <EyeOff className="mr-1 h-4 w-4" /> : <Eye className="mr-1 h-4 w-4" />}
                        {showBackup ? "Hide" : "Show"} Backup Codes
                      </Button>
                      {!isDisabling && (
                        <Button variant="destructive" size="sm" onClick={() => setIsDisabling(true)}>
                          Disable 2FA
                        </Button>
                      )}
                    </div>

                    {isDisabling && (
                      <div className="flex flex-col gap-3 max-w-sm rounded-lg border bg-muted/50 p-4">
                        <div className="space-y-1.5">
                          <Label htmlFor="disable-pass">Confirm Password to Disable</Label>
                          <Input
                            id="disable-pass"
                            type="password"
                            placeholder="Your password"
                            value={disablePassword}
                            onChange={(e) => setDisablePassword(e.target.value)}
                          />
                        </div>
                        <div className="flex gap-2">
                          <Button variant="destructive" onClick={handleDisable} disabled={disableMutation.isPending} className="flex-1">
                            {disableMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                            Confirm Disable
                          </Button>
                          <Button variant="ghost" onClick={() => setIsDisabling(false)} className="flex-1">Cancel</Button>
                        </div>
                      </div>
                    )}

                    {showBackup && backupCodes.length > 0 && (
                      <Card className="bg-muted">
                        <CardContent className="py-3">
                          <p className="text-xs font-medium mb-2">Save these backup codes in a safe place:</p>
                          <div className="grid grid-cols-2 gap-2">
                            {backupCodes.map((code, i) => (
                              <code key={i} className="text-xs font-mono bg-background px-2 py-1.5 rounded border">{code}</code>
                            ))}
                          </div>
                          <Button
                            variant="ghost"
                            size="sm"
                            className="mt-3 w-full"
                            onClick={() => {
                              navigator.clipboard.writeText(backupCodes.join("\n"));
                              toast({ title: "Copied to clipboard" });
                            }}
                          >
                            <Copy className="mr-2 h-3 w-3" /> Copy All Codes
                          </Button>
                        </CardContent>
                      </Card>
                    )}
                  </div>
                )}

                {!is2FAEnabled && step === "idle" && (
                  <div className="flex flex-col gap-4">
                    <p className="text-sm text-muted-foreground">
                      Protect your account by requiring a security code whenever you sign in.
                    </p>
                    <Button onClick={handleStartSetup} className="w-fit">
                      <Smartphone className="mr-2 h-4 w-4" /> Enable 2FA
                    </Button>
                  </div>
                )}

                {step === "setup" && (
                  <div className="space-y-6">
                    {isLoadingSetup ? (
                      <div className="flex flex-col items-center justify-center py-12 space-y-4">
                        <Loader2 className="h-10 w-10 animate-spin text-primary" />
                        <p className="text-sm font-medium text-muted-foreground">Preparing your secure connection...</p>
                      </div>
                    ) : (
                      <>
                        <div className="space-y-6">
                          <div className="flex gap-4">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground text-sm font-bold">1</div>
                            <div className="space-y-1">
                              <p className="font-semibold leading-none pt-1.5">Scan the QR Code</p>
                              <p className="text-sm text-muted-foreground">
                                Open your authenticator app (like Google Authenticator, Authy, or Microsoft Authenticator) and scan the image below.
                              </p>
                            </div>
                          </div>

                          <div className="flex flex-col items-center justify-center py-2">
                            <div className="relative group">
                              <div className="absolute -inset-1 bg-gradient-to-r from-primary/20 to-primary/10 rounded-2xl blur opacity-75 group-hover:opacity-100 transition duration-1000 group-hover:duration-200"></div>
                              <div className="relative rounded-xl border bg-white p-6 shadow-xl">
                                {setupData?.qr_code_svg ? (
                                  <div
                                    dangerouslySetInnerHTML={{ __html: setupData.qr_code_svg }}
                                    className="w-[180px] h-[180px] [&>svg]:w-full [&>svg]:h-full"
                                  />
                                ) : (
                                  <div className="w-[180px] h-[180px] flex items-center justify-center text-destructive bg-destructive/5 rounded-lg border border-dashed border-destructive/20">
                                    <p className="text-xs text-center px-4">Connection interrupted.<br />Please try again.</p>
                                  </div>
                                )}
                              </div>
                            </div>
                          </div>

                          <div className="flex gap-4">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground text-sm font-bold border">2</div>
                            <div className="space-y-3 flex-1">
                              <p className="font-semibold leading-none pt-1.5">Manual Setup Option</p>
                              <p className="text-sm text-muted-foreground">
                                If you're unable to scan the code, you can manually enter this secret key into your app:
                              </p>
                              <div className="flex items-center gap-2 group">
                                <code className="flex-1 bg-muted/50 px-4 py-3 rounded-lg text-sm font-mono border tracking-[0.2em] font-bold text-center select-all">
                                  {setupData?.secret}
                                </code>
                                <Button
                                  variant="outline"
                                  size="icon"
                                  className="h-11 w-11 shrink-0"
                                  onClick={() => {
                                    if (setupData) {
                                      navigator.clipboard.writeText(setupData.secret);
                                      toast({ title: "Secret key copied", description: "You can now paste it in your app." });
                                    }
                                  }}
                                >
                                  <Copy className="h-4 w-4" />
                                </Button>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div className="flex gap-3 pt-6 border-t mt-4">
                          <Button variant="ghost" onClick={() => setStep("idle")} className="flex-1">
                            Go Back
                          </Button>
                          <Button onClick={() => setStep("verify")} disabled={!setupData} className="flex-1 shadow-lg shadow-primary/20">
                            I've scanned it, continue
                          </Button>
                        </div>
                      </>
                    )}
                  </div>
                )}

                {step === "verify" && (
                  <div className="space-y-6">
                    <div className="space-y-1">
                      <p className="font-semibold">Step 2: Verify Setup</p>
                      <p className="text-sm text-muted-foreground">Enter the 6-digit code from your app to confirm.</p>
                    </div>

                    <div className="flex justify-center py-4">
                      <InputOTP maxLength={6} value={otpCode} onChange={setOtpCode}>
                        <InputOTPGroup className="gap-2">
                          <InputOTPSlot index={0} className="w-12 h-14 text-xl border-2" />
                          <InputOTPSlot index={1} className="w-12 h-14 text-xl border-2" />
                          <InputOTPSlot index={2} className="w-12 h-14 text-xl border-2" />
                          <InputOTPSlot index={3} className="w-12 h-14 text-xl border-2" />
                          <InputOTPSlot index={4} className="w-12 h-14 text-xl border-2" />
                          <InputOTPSlot index={5} className="w-12 h-14 text-xl border-2" />
                        </InputOTPGroup>
                      </InputOTP>
                    </div>

                    <div className="flex gap-3 pt-4 border-t">
                      <Button variant="outline" onClick={() => { setStep("setup"); setOtpCode(""); }} className="flex-1">Back</Button>
                      <Button
                        onClick={handleVerify}
                        disabled={otpCode.length !== 6 || confirmMutation.isPending}
                        className="flex-1"
                      >
                        {confirmMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                        Confirm & Enable
                      </Button>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Password Change */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Change Password</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2 max-w-sm">
                  <Label>New Password</Label>
                  <Input type="password" placeholder="••••••••" value={newPassword} onChange={(e) => setNewPassword(e.target.value)} />
                </div>
                <div className="space-y-2 max-w-sm">
                  <Label>Confirm New Password</Label>
                  <Input type="password" placeholder="••••••••" value={confirmPassword} onChange={(e) => setConfirmPassword(e.target.value)} />
                </div>
                <Button onClick={handlePasswordChange} disabled={updating}>
                  {updating ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                  Update Password
                </Button>
              </CardContent>
            </Card>

            {/* Active Sessions */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Active Sessions</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex items-center justify-between rounded-lg border p-4 bg-muted/20">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-background rounded-full border shadow-sm">
                      <Smartphone className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                      <p className="text-sm font-medium">Current Session</p>
                      <p className="text-xs text-muted-foreground">{navigator.userAgent.split(" ").pop()}</p>
                    </div>
                  </div>
                  <Badge variant="default" className="bg-green-100 text-green-700 hover:bg-green-100">Live now</Badge>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="allowlist" className="mt-4">
            <AllowlistPanel />
          </TabsContent>

          <TabsContent value="api-keys" className="mt-4">
            <ApiKeysPanel />
          </TabsContent>
        </Tabs>
      </div>
    </>
  );
}

// ── IP Allowlist Panel (Moved from Settings.tsx) ───────────────────────────

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
      setSubmitting(false);
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
              {(entries || []).map((e: any) => (
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

// ── API Keys Panel (Moved from Settings.tsx) ─────────────────────────────

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
                {(keys || []).map((k: any) => (
                  <TableRow key={k.id}>
                    <TableCell className="font-medium text-sm">{k.name}</TableCell>
                    <TableCell className="font-mono text-xs">{k.key_prefix}</TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        {k.allowed_scopes?.map((s: string) => (
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
