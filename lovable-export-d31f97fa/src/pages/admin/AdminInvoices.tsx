import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api/client";
import { z } from "zod";

const AdminFinancialRecordSchema = z.object({
  id: z.string(),
  db_id: z.number(),
  source: z.enum(["invoice", "transaction"]),
  user: z.object({
    name: z.string().nullable(),
    email: z.string(),
  }).nullable(),
  amount: z.number(),
  currency: z.string(),
  status: z.string(),
  description: z.string(),
  reference: z.string().nullable(),
  created_at: z.string(),
  type: z.string().optional(), // only for source=transaction
});

import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Search, Download, Eye, FileText, User as UserIcon, Calendar, Hash, Receipt, CheckCircle2, XCircle, Clock, AlertCircle } from "lucide-react";
import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useToast } from "@/hooks/use-toast";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "destructive"> = {
  credit: "default",
  debit: "secondary"
};

export default function AdminInvoices() {
  const [search, setSearch] = useState("");
  const [selected, setSelected] = useState<any>(null);
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const { data: records = [], isLoading } = useQuery({
    queryKey: ["admin-invoices"],
    queryFn: () => api.get("/admin/invoices", z.array(AdminFinancialRecordSchema)),
  });

  const updateStatusMutation = useMutation({
    mutationFn: ({ id, status, source }: { id: number; status: string; source: string }) =>
      api.patch(`/admin/invoices/${id}/status`, { status, source }, z.any()),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin-invoices"] });
      toast({ title: "Status Updated", description: "The invoice status has been successfully updated." });
      setSelected(null);
    },
    onError: (error: any) => {
      toast({
        title: "Update Failed",
        description: error.message || "Could not update invoice status.",
        variant: "destructive",
      });
    },
  });

  const filtered = records.filter((r: any) => {
    const q = search.toLowerCase();
    return (
      r.id.toLowerCase().includes(q) ||
      r.user?.email.toLowerCase().includes(q) ||
      r.description.toLowerCase().includes(q) ||
      (r.reference && r.reference.toLowerCase().includes(q))
    );
  });

  return (
    <>
      <SEOHead title="All Transactions" noindex />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">Financial History</h1>
          <Button variant="outline" size="sm" className="gap-1.5"><Download className="h-4 w-4" /> Export CSV</Button>
        </div>

        <div className="relative max-w-sm">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Search by ID, email, or description..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-9"
          />
        </div>

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Reference</TableHead>
                  <TableHead>User</TableHead>
                  <TableHead>Description</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  <TableRow><TableCell colSpan={6} className="h-24 text-center">Loading financial records...</TableCell></TableRow>
                ) : filtered.map((r: any) => (
                  <TableRow key={r.id}>
                    <TableCell className="font-mono text-[10px]">
                      <div className="flex flex-col">
                        <span className="font-bold">{r.id}</span>
                        {r.reference && <span className="text-muted-foreground truncate max-w-[120px]">{r.reference}</span>}
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col">
                        <span className="text-sm font-medium">{r.user?.name || "Unknown"}</span>
                        <span className="text-xs text-muted-foreground">{r.user?.email}</span>
                      </div>
                    </TableCell>
                    <TableCell className="text-sm">
                      <div className="flex items-center gap-2">
                        {r.source === 'invoice' ? <Receipt className="h-3 w-3 text-blue-500" /> : <Hash className="h-3 w-3 text-gray-400" />}
                        <span className="truncate max-w-[200px]">{r.description}</span>
                      </div>
                    </TableCell>
                    <TableCell className="text-sm font-semibold">
                      {r.currency} {Number(r.amount).toFixed(2)}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          r.status === 'paid' ? 'default' :
                            r.status === 'pending' ? 'secondary' :
                              r.status === 'cancelled' || r.status === 'failed' ? 'destructive' : 'outline'
                        }
                        className="capitalize"
                      >
                        {r.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="icon" onClick={() => setSelected(r)}>
                        <Eye className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
                {!isLoading && filtered.length === 0 && (
                  <TableRow><TableCell colSpan={6} className="h-24 text-center text-muted-foreground">No transactions found.</TableCell></TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>

      <Dialog open={!!selected} onOpenChange={(open) => !open && setSelected(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Receipt className="h-5 w-5 text-primary" /> Transaction Details
            </DialogTitle>
            <DialogDescription>Full record for transaction #TX-{selected?.id}</DialogDescription>
          </DialogHeader>
          {selected && (
            <div className="space-y-4 py-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                  <p className="text-[11px] uppercase text-muted-foreground font-semibold flex items-center gap-1">
                    <Hash className="h-3 w-3" /> Record ID
                  </p>
                  <p className="text-sm font-mono font-medium">{selected.id}</p>
                </div>
                <div className="space-y-1">
                  <p className="text-[11px] uppercase text-muted-foreground font-semibold flex items-center gap-1">
                    <Calendar className="h-3 w-3" /> Date & Time
                  </p>
                  <p className="text-sm">{new Date(selected.created_at).toLocaleString()}</p>
                </div>
              </div>

              <div className="rounded-lg border p-3 bg-muted/30 space-y-3">
                <div className="flex items-start gap-3">
                  <UserIcon className="h-4 w-4 text-muted-foreground mt-0.5" />
                  <div className="space-y-0.5">
                    <p className="text-xs text-muted-foreground">Customer</p>
                    <p className="text-sm font-medium">{selected.user?.name || "N/A"}</p>
                    <p className="text-[11px] text-muted-foreground font-mono">{selected.user?.email}</p>
                  </div>
                </div>

                <div className="flex items-start gap-3">
                  <FileText className="h-4 w-4 text-muted-foreground mt-0.5" />
                  <div className="space-y-0.5">
                    <p className="text-xs text-muted-foreground">Description</p>
                    <p className="text-sm">{selected.description}</p>
                    {selected.reference && (
                      <p className="text-[10px] text-muted-foreground font-mono break-all italic">Ref: {selected.reference}</p>
                    )}
                  </div>
                </div>
              </div>

              <div className="space-y-3 border-t pt-4">
                <p className="text-sm font-medium">Manage Status</p>
                <div className="flex items-center gap-2">
                  <Select
                    defaultValue={selected.status}
                    onValueChange={(val) => updateStatusMutation.mutate({ id: selected.db_id, status: val, source: selected.source })}
                    disabled={updateStatusMutation.isPending}
                  >
                    <SelectTrigger className="w-full">
                      <SelectValue placeholder="Select Status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="paid">
                        <div className="flex items-center gap-2 text-green-600">
                          <CheckCircle2 className="h-4 w-4" /> Paid
                        </div>
                      </SelectItem>
                      <SelectItem value="unpaid">
                        <div className="flex items-center gap-2 text-orange-600">
                          <Clock className="h-4 w-4" /> Unpaid
                        </div>
                      </SelectItem>
                      <SelectItem value="pending">
                        <div className="flex items-center gap-2 text-blue-600">
                          <AlertCircle className="h-4 w-4" /> Pending
                        </div>
                      </SelectItem>
                      <SelectItem value="cancelled">
                        <div className="flex items-center gap-2 text-red-600">
                          <XCircle className="h-4 w-4" /> Cancelled
                        </div>
                      </SelectItem>
                      <SelectItem value="failed">
                        <div className="flex items-center gap-2 text-red-800">
                          <XCircle className="h-4 w-4" /> Failed
                        </div>
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <p className="text-[10px] text-muted-foreground">
                  Changing the status here will update the record and customer's portal view.
                  {selected.source === 'transaction' && " For transactions, marking as 'cancelled' or 'failed' will reverse the balance impact."}
                </p>
              </div>

              <div className="flex items-center justify-between border-t pt-4">
                <span className="text-sm font-medium">Total Amount</span>
                <div className="text-right">
                  <p className="text-lg font-bold">
                    {selected.currency} {Number(selected.amount).toFixed(2)}
                  </p>
                  <Badge className="capitalize text-[10px] h-4" variant={selected.status === 'paid' ? 'default' : 'secondary'}>
                    {selected.status}
                  </Badge>
                </div>
              </div>

              <div className="flex gap-2 pt-2">
                <Button className="w-full gap-2" variant="outline" onClick={() => window.print()}>
                  <Download className="h-4 w-4" /> Download PDF
                </Button>
                <Button className="w-full" variant="outline" onClick={() => setSelected(null)}>Close</Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}
