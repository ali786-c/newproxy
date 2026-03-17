import { useState } from "react";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Plus, Pencil, Trash2, X } from "lucide-react";
import { toast } from "@/hooks/use-toast";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, MessageSchema } from "@/lib/api/client";
import { z } from "zod";

const ProductSchema = z.object({
  id: z.coerce.string(),
  name: z.string(),
  type: z.string(),
  price: z.coerce.number(),
  is_active: z.coerce.number().or(z.boolean()).optional().default(true),
  is_trial: z.coerce.boolean().optional().default(false),
  sort_order: z.coerce.number().optional().default(0),
  evomi_product_id: z.string().nullable().optional(),
  tagline: z.string().nullable().optional(),
  features: z.array(z.string()).nullable().optional(),
  unit: z.string().nullable().optional(),
  base_cost: z.coerce.number().nullable().optional(),
  markup: z.coerce.number().nullable().optional(),
  volume_discounts: z.array(
    z.object({
      min_qty: z.coerce.number(),
      price: z.coerce.number()
    })
  ).nullable().optional(),
});

const PaginatedProductSchema = z.object({
  data: z.array(ProductSchema),
  current_page: z.number(),
  last_page: z.number(),
  total: z.number(),
});

interface Product {
  id: string;
  name: string;
  proxy_type: string;
  unit: "GB" | "IP" | "Month";
  base_cost_eur: number;
  sell_price_eur: number;
  markup_pct: number;
  is_active: boolean;
}

type FormData = {
  name: string;
  proxy_type: string;
  unit: "GB" | "IP" | "Month";
  base_cost_eur: string;
  markup_pct: string;
  is_active: boolean;
  is_trial: boolean;
  sort_order: string;
  evomi_product_id: string;
  tagline: string;
  features: string[];
  volume_discounts: { min_qty: number; price: number }[];
};

const EMPTY_FORM: FormData = {
  name: "",
  proxy_type: "rp",
  unit: "GB",
  base_cost_eur: "",
  markup_pct: "0",
  is_active: true,
  is_trial: false,
  sort_order: "0",
  evomi_product_id: "",
  tagline: "",
  features: [],
  volume_discounts: []
};

export default function AdminProducts() {
  const queryClient = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [form, setForm] = useState<FormData>(EMPTY_FORM);

  const [page, setPage] = useState(1);

  const { data: paginatedData, isLoading } = useQuery({
    queryKey: ["admin-products", page],
    queryFn: async () => {
      const resp = await api.get(`/admin/products?page=${page}`, PaginatedProductSchema);
      return {
        ...resp,
        data: resp.data.map((p) => ({
          id: String(p.id),
          name: p.name,
          proxy_type: p.type,
          unit: p.unit || (p.type === "isp" ? "IP" : p.type === "dc_unmetered" ? "Month" : "GB"),
          base_cost_eur: Number(p.base_cost || p.price),
          markup_pct: Number(p.markup || 0),
          sell_price_eur: Number(p.price),
          is_active: Boolean(p.is_active),
          is_trial: Boolean(p.is_trial),
          sort_order: p.sort_order ?? 0,
          evomi_product_id: p.evomi_product_id,
          tagline: p.tagline || "",
          features: p.features || [],
          volume_discounts: p.volume_discounts || []
        }))
      };
    },
  });

  const products = paginatedData?.data || [];

  const mutation = useMutation({
    mutationFn: (data: any) => {
      if (editId) return api.put(`/admin/products/${editId}`, ProductSchema, data);
      return api.post("/admin/products", ProductSchema, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin-products"] });
      setModalOpen(false);
      toast({ title: `Product ${editId ? "updated" : "created"} successfully` });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/products/${id}`, MessageSchema),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin-products"] });
      toast({ title: "Product deleted" });
    },
    onError: (err: any) => {
      toast({
        title: "Deletion failed",
        description: err.response?.data?.error || err.message,
        variant: "destructive"
      });
    }
  });

  if (isLoading) return <div className="p-8 text-center text-muted-foreground">Loading products...</div>;

  const openCreate = () => {
    setEditId(null);
    setForm(EMPTY_FORM);
    setModalOpen(true);
  };

  const handleDelete = (id: string) => {
    if (window.confirm("Are you sure you want to delete this product?")) {
      deleteMutation.mutate(id);
    }
  };

  const openEdit = (p: any) => {
    setEditId(p.id);
    setForm({
      name: p.name,
      proxy_type: p.proxy_type,
      unit: p.unit,
      base_cost_eur: String(p.base_cost_eur),
      markup_pct: String(p.markup_pct),
      is_active: p.is_active,
      is_trial: p.is_trial || false,
      sort_order: String(p.sort_order ?? 0),
      evomi_product_id: p.evomi_product_id || "",
      tagline: p.tagline || "",
      features: p.features || [],
      volume_discounts: p.volume_discounts || []
    });
    setModalOpen(true);
  };

  const computeSellPrice = () => {
    const base = parseFloat(form.base_cost_eur) || 0;
    const markup = parseFloat(form.markup_pct) || 0;
    return (base * (1 + markup / 100)).toFixed(2);
  };

  const handleSave = () => {
    // FIX P2 + P7: Frontend Validation
    if (!form.name.trim()) {
      return toast({ title: "Name is required", variant: "destructive" });
    }
    if (!form.evomi_product_id.trim()) {
      return toast({ title: "Evomi Product ID is required", variant: "destructive" });
    }
    const price = parseFloat(computeSellPrice());
    if (isNaN(price) || price < 0) {
      return toast({ title: "Invalid price", variant: "destructive" });
    }

    mutation.mutate({
      name: form.name.trim(),
      type: form.proxy_type,
      price: form.is_trial ? 0 : price,
      is_active: form.is_active,
      is_trial: form.is_trial,
      sort_order: parseInt(form.sort_order) || 0,
      evomi_product_id: form.evomi_product_id.trim(),
      tagline: form.tagline.trim(),
      unit: form.unit,
      base_cost: form.is_trial ? 0 : parseFloat(form.base_cost_eur),
      markup: form.is_trial ? 0 : parseFloat(form.markup_pct),
      features: form.features.filter(f => f.trim() !== ""),
      volume_discounts: (!form.is_trial && form.volume_discounts.length > 0) ? form.volume_discounts : null
    });
  };

  return (
    <>
      <SEOHead title="Admin — Products" noindex />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Products</h1>
            <p className="text-sm text-muted-foreground">Manage proxy product catalog. All prices flat-rate in EUR with 30% markup over base cost.</p>
          </div>
          <Button onClick={openCreate}>
            <Plus className="mr-2 h-4 w-4" /> Add Product
          </Button>
        </div>

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Product</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Unit</TableHead>
                  <TableHead className="text-right">Base Cost</TableHead>
                  <TableHead className="text-right">Markup</TableHead>
                  <TableHead className="text-right">Sell Price</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Sort Order</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {products?.map((p: any) => (
                  <TableRow key={p.id}>
                    <TableCell className="font-medium">
                      <div className="flex items-center gap-2">
                        {p.name}
                        {p.is_trial && (
                          <Badge variant="outline" className="text-xs border-emerald-500 text-emerald-600 bg-emerald-50">
                            Free Trial
                          </Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell className="capitalize">{p.proxy_type}</TableCell>
                    <TableCell>per {p.unit}</TableCell>
                    <TableCell className="text-right">{p.is_trial ? '—' : `€${p.base_cost_eur.toFixed(2)}`}</TableCell>
                    <TableCell className="text-right">{p.is_trial ? '—' : `${p.markup_pct}%`}</TableCell>
                    <TableCell className="text-right font-semibold">{p.is_trial ? <span className="text-emerald-600 font-bold">FREE</span> : `€${p.sell_price_eur.toFixed(2)}/${p.unit}`}</TableCell>
                    <TableCell>
                      <Badge variant={p.is_active ? "default" : "secondary"}>
                        {p.is_active ? "Active" : "Inactive"}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">{p.sort_order ?? 0}</Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1 justify-end">
                        <Button variant="ghost" size="icon" onClick={() => openEdit(p)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" onClick={() => handleDelete(p.id)}>
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            {paginatedData && paginatedData.last_page > 1 && (
              <div className="flex items-center justify-between px-4 py-3 border-t">
                <div className="text-sm text-muted-foreground">
                  Showing page {paginatedData.current_page} of {paginatedData.last_page} ({paginatedData.total} products)
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage(p => Math.max(1, p - 1))}
                    disabled={page === 1}
                  >
                    Previous
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage(p => Math.min(paginatedData.last_page, p + 1))}
                    disabled={page === paginatedData.last_page}
                  >
                    Next
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Pricing Model Info */}
        <Card>
          <CardContent className="py-4">
            <p className="text-sm font-medium">Pricing Model</p>
            <p className="mt-1 text-xs text-muted-foreground">
              All products use flat-rate pricing in EUR but support optional volume-based discounting.
              The sell price is calculated as base cost × (1 + markup%).
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Create / Edit Modal */}
      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editId ? "Edit Product" : "Create Product"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2 max-h-[70vh] overflow-y-auto px-1">
            <div className="space-y-1">
              <Label>Name <span className="text-destructive">*</span></Label>
              <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </div>
            <div className="space-y-1">
              <Label>Tagline / Subtitle</Label>
              <Input
                placeholder="e.g. 100% ethical residential proxies"
                value={form.tagline}
                onChange={(e) => setForm({ ...form, tagline: e.target.value })}
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label>Proxy Type</Label>
                <Select value={form.proxy_type} onValueChange={(v) => setForm({ ...form, proxy_type: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="rp">Residential (rp)</SelectItem>
                    <SelectItem value="dc">Datacenter (dc)</SelectItem>
                    <SelectItem value="sdc">Shared Datacenter (sdc)</SelectItem>
                    <SelectItem value="mp">Mobile (mp)</SelectItem>
                    <SelectItem value="isp">General ISP (isp)</SelectItem>
                    <SelectItem value="isp_shared">Shared Static ISP (isp_shared)</SelectItem>
                    <SelectItem value="isp_private">Private Static ISP (isp_private)</SelectItem>
                    <SelectItem value="isp_virgin">Virgin Static ISP (isp_virgin)</SelectItem>
                    <SelectItem value="dc_ipv6">Datacenter IPv6 (dc_ipv6)</SelectItem>
                    <SelectItem value="dc_unmetered">Datacenter Unmetered (dc_unmetered)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label>Billing Unit</Label>
                <Select value={form.unit} onValueChange={(v: "GB" | "IP" | "Month") => setForm({ ...form, unit: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="GB">Per GB</SelectItem>
                    <SelectItem value="IP">Per IP</SelectItem>
                    <SelectItem value="Month">Per Month</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label>Base Cost (€)</Label>
                <Input type="number" step="0.01" value={form.base_cost_eur} onChange={(e) => setForm({ ...form, base_cost_eur: e.target.value })} />
              </div>
              <div className="space-y-1">
                <Label>Markup (%)</Label>
                <Input type="number" value={form.markup_pct} onChange={(e) => setForm({ ...form, markup_pct: e.target.value })} />
              </div>
            </div>
            <div className="rounded-md bg-muted/50 p-3">
              <p className="text-sm">Sell Price: <span className="font-bold text-primary">€{computeSellPrice()}/{form.unit}</span></p>
            </div>
            <div className="space-y-1">
              <Label>Evomi Product ID (External) <span className="text-destructive">*</span></Label>
              <Input
                placeholder="e.g. residential_premium"
                value={form.evomi_product_id}
                onChange={(e) => setForm({ ...form, evomi_product_id: e.target.value })}
              />
            </div>

            <div className="space-y-2 border rounded-md p-3">
              <div className="flex justify-between items-center">
                <Label>Features List</Label>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setForm({ ...form, features: [...form.features, ""] })}
                >
                  <Plus className="h-3 w-3 mr-1" /> Add Feature
                </Button>
              </div>
              {form.features.length === 0 && (
                <p className="text-xs text-muted-foreground italic">No features added. Core defaults will be shown.</p>
              )}
              {form.features.map((feat, idx) => (
                <div key={idx} className="flex items-center gap-2">
                  <Input
                    value={feat}
                    placeholder="e.g. 50M+ Real IPs"
                    onChange={(e) => {
                      const newFeats = [...form.features];
                      newFeats[idx] = e.target.value;
                      setForm({ ...form, features: newFeats });
                    }}
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="shrink-0 text-destructive"
                    onClick={() => {
                      const newFeats = form.features.filter((_, i) => i !== idx);
                      setForm({ ...form, features: newFeats });
                    }}
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              ))}
            </div>

            <div className="space-y-2 border rounded-md p-3">
              <div className="flex justify-between items-center">
                <Label>Volume Discounts</Label>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setForm({ ...form, volume_discounts: [...form.volume_discounts, { min_qty: 100, price: 0.99 }] })}
                >
                  <Plus className="h-3 w-3 mr-1" /> Add Tier
                </Button>
              </div>
              {form.volume_discounts.length === 0 && (
                <p className="text-xs text-muted-foreground italic">No volume discounts added. Pricing is flat-rate.</p>
              )}
              {form.volume_discounts.map((discount, idx) => (
                <div key={`discount-${idx}`} className="flex items-center gap-2">
                  <div className="grid grid-cols-2 gap-2 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-xs text-muted-foreground w-16">Min Qty:</span>
                      <Input
                        type="number"
                        min="1"
                        value={discount.min_qty}
                        onChange={(e) => {
                          const newDiscounts = [...form.volume_discounts];
                          newDiscounts[idx].min_qty = Number(e.target.value);
                          setForm({ ...form, volume_discounts: newDiscounts });
                        }}
                      />
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs text-muted-foreground w-12">Price:</span>
                      <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={discount.price}
                        onChange={(e) => {
                          const newDiscounts = [...form.volume_discounts];
                          newDiscounts[idx].price = Number(e.target.value);
                          setForm({ ...form, volume_discounts: newDiscounts });
                        }}
                      />
                    </div>
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="shrink-0 text-destructive"
                    onClick={() => {
                      const newDiscounts = form.volume_discounts.filter((_, i) => i !== idx);
                      setForm({ ...form, volume_discounts: newDiscounts });
                    }}
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              ))}
            </div>
            <div className="flex items-center gap-2">
              <Switch checked={form.is_trial} onCheckedChange={(v) => setForm({ ...form, is_trial: v, base_cost_eur: v ? "0" : form.base_cost_eur, markup_pct: v ? "0" : form.markup_pct })} />
              <Label>Is Free Trial Product <span className="text-xs text-muted-foreground">(price locked to €0, no billing)</span></Label>
            </div>

            <div className="space-y-2">
              <Label htmlFor="sort_order">Sort Order (Lower shows first)</Label>
              <Input
                id="sort_order"
                type="number"
                value={form.sort_order}
                onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
                placeholder="0"
              />
            </div>

            <div className="flex items-center gap-2">
              <Switch checked={form.is_active} onCheckedChange={(v) => setForm({ ...form, is_active: v })} />
              <Label>Active</Label>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button onClick={handleSave}>{editId ? "Update" : "Create"}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
