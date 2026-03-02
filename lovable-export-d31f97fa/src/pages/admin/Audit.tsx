import { useState } from "react";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Search, MapPin } from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api/client";
import { z } from "zod";

const AuditLogSchema = z.object({
  id: z.number(),
  action: z.string(),
  details: z.string().nullable(),
  target_user_id: z.number().nullable(),
  ip_address: z.string().nullable(),
  user_agent: z.string().nullable(),
  geo_country: z.string().nullable(),
  geo_city: z.string().nullable(),
  created_at: z.string(),
  admin: z.object({
    name: z.string()
  }).nullable()
});

const ACTION_COLORS: Record<string, "default" | "destructive" | "secondary" | "outline"> = {
  ban_user: "destructive",
  deduct: "destructive",
  unban_user: "default",
  top_up: "default",
  update: "secondary",
  reply: "secondary",
  create: "default",
  update_balance: "default",
  update_settings: "secondary",
  create_email_template: "default",
  update_email_template: "secondary",
  delete_email_template: "destructive",
  reply_ticket: "secondary",
  update_ticket_status: "secondary",
  delete_ticket: "destructive",
};

export default function AdminAudit() {
  const [search, setSearch] = useState("");
  const [actionFilter, setActionFilter] = useState("all");

  const { data: entries, isLoading } = useQuery({
    queryKey: ["audit-log", actionFilter],
    queryFn: async () => {
      const data = await api.get(`/admin/logs?action=${actionFilter}`, z.array(AuditLogSchema));
      return data.map((e: any) => ({
        id: String(e.id),
        created_at: e.created_at,
        action: e.action,
        target_id: String(e.target_user_id || ""),
        ip_address: e.ip_address || "Internal",
        admin_name: e.admin?.name ?? "System",
        geo_country: e.geo_country,
        geo_city: e.geo_city,
        metadata: { details: e.details }
      }));
    },
  });

  const filtered = (entries ?? []).filter((entry) => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (
      entry.action.toLowerCase().includes(q) ||
      entry.target_id?.toLowerCase().includes(q) ||
      entry.ip_address?.toLowerCase().includes(q) ||
      entry.geo_country?.toLowerCase().includes(q)
    );
  });

  const actionTypes = [...new Set((entries ?? []).map((e) => e.action.split("_")[0]))];

  const formatTime = (ts: string) => {
    const d = new Date(ts);
    return d.toLocaleString("en-US", { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
  };

  return (
    <>
      <SEOHead title="Admin — Audit Log" noindex />
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold">Audit Log</h1>
          <p className="text-sm text-muted-foreground">Review all system-wide activity with IP & geo tracking.</p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <div className="relative flex-1 min-w-[200px]">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input placeholder="Search action, target, IP, country…" value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9" />
          </div>
          <Select value={actionFilter} onValueChange={setActionFilter}>
            <SelectTrigger className="w-[150px]"><SelectValue placeholder="Action type" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All actions</SelectItem>
              {actionTypes.map((t) => (
                <SelectItem key={t} value={t} className="capitalize">{t}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Timestamp</TableHead>
                  <TableHead>Action</TableHead>
                  <TableHead>Target</TableHead>
                  <TableHead>IP Address</TableHead>
                  <TableHead>Location</TableHead>
                  <TableHead>Meta</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading && (
                  <TableRow><TableCell colSpan={6} className="h-24 text-center text-muted-foreground">Loading…</TableCell></TableRow>
                )}
                {!isLoading && filtered.map((entry) => {
                  const actionVerb = entry.action.split("_")[0];
                  return (
                    <TableRow key={entry.id}>
                      <TableCell className="text-sm text-muted-foreground whitespace-nowrap">{formatTime(entry.created_at)}</TableCell>
                      <TableCell>
                        <Badge variant={ACTION_COLORS[actionVerb] ?? "outline"} className="text-xs">{entry.action}</Badge>
                      </TableCell>
                      <TableCell className="text-sm font-mono text-xs">{entry.target_id?.slice(0, 8) ?? "—"}</TableCell>
                      <TableCell className="text-sm font-mono text-xs">{entry.ip_address ?? "—"}</TableCell>
                      <TableCell className="text-sm">
                        {entry.geo_country ? (
                          <span className="flex items-center gap-1 text-xs">
                            <MapPin className="h-3 w-3" />
                            {entry.geo_city ? `${entry.geo_city}, ` : ""}{entry.geo_country}
                          </span>
                        ) : "—"}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground max-w-[250px] break-words">
                        {entry.metadata?.details ?? "—"}
                      </TableCell>
                    </TableRow>
                  );
                })}
                {!isLoading && filtered.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">No audit entries found.</TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
