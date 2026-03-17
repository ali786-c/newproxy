import { useState, useCallback, useMemo, useEffect } from "react";
import { Link, useSearchParams } from "react-router-dom";

import { SEOHead } from "@/components/seo/SEOHead";
import { ErrorBanner } from "@/components/shared/ErrorBanner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { useQueryClient } from "@tanstack/react-query";
import { Copy, Download, Loader2, CreditCard, Wallet, Bitcoin } from "lucide-react";
import { toast } from "@/hooks/use-toast";
import { useProducts, useGenerateProxy, useIspStock, useOrderIsp } from "@/hooks/use-backend";
import { clientApi } from "@/lib/api/dashboard";
import evomiCountries from "@/lib/data/countries.json";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import { usePaymentConfig } from "@/contexts/PaymentConfigContext";
import { useCurrency } from "@/contexts/CurrencyContext";
import { useI18n } from "@/contexts/I18nContext";
import { ManualCryptoDialog } from "@/components/shared/ManualCryptoDialog";
import { formatProxyLine } from "@/lib/utils";

const TYPE_MAP: Record<string, string> = {
  rp: "residential",
  mp: "mobile",
  dc: "datacenter",
  sdc: "shared_datacenter",
  dc_ipv6: "datacenter_ipv6",
  dc_unmetered: "datacenter_unmetered",
};

const SLUG_MAP: Record<string, string> = {
  rp: "residential",
  mp: "mobile",
  dc: "datacenter",
  sdc: "shared-datacenter",
  dc_ipv6: "datacenter-ipv6",
  dc_unmetered: "datacenter-unmetered",
};


function generateCurl(p: any) {
  return `curl -x http://${p.username}:${p.password}@${p.host}:${p.port} https://httpbin.org/ip`;
}

function generatePython(p: any) {
  return `import requests

proxies = {
    "http": "http://${p.username}:${p.password}@${p.host}:${p.port}",
    "https": "http://${p.username}:${p.password}@${p.host}:${p.port}",
}
response = requests.get("https://httpbin.org/ip", proxies=proxies)
print(response.json())`;
}

function generateNode(p: any) {
  return `const HttpsProxyAgent = require('https-proxy-agent');

const agent = new HttpsProxyAgent(
  'http://${p.username}:${p.password}@${p.host}:${p.port}'
);

fetch('https://httpbin.org/ip', { agent })
  .then(res => res.json())
  .then(console.log);`;
}

export default function Proxies() {
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const { data: products } = useProducts();
  const generateProxy = useGenerateProxy();

  // Refresh data if user returns from a successful payment
  useEffect(() => {
    if (searchParams.get("success") === "true") {
      queryClient.invalidateQueries({ queryKey: ["me"] });
      queryClient.invalidateQueries({ queryKey: ["stats"] });
      queryClient.invalidateQueries({ queryKey: ["proxies"] }); // If you have a proxy list query
    }
  }, [searchParams, queryClient]);

  const [product, setProduct] = useState(""); // This will store Product ID
  const [productType, setProductType] = useState("residential"); // For geo-filtering
  const [country, setCountry] = useState("");
  const [city, setCity] = useState("");
  const [sessionType, setSessionType] = useState("rotating");
  const [quantity, setQuantity] = useState(1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [proxies, setProxies] = useState<any[]>([]);
  const [ispCountry, setIspCountry] = useState("");
  const [ispCity, setIspCity] = useState("");
  const [ispName, setIspName] = useState("");
  const [ispMonths, setIspMonths] = useState(1);
  const { format } = useCurrency();
  const { t } = useI18n();

  const selectedProductData = (products ?? []).find((p: any) => p.id.toString() === product);

  const isIspProduct = productType?.startsWith("isp_") ?? false;
  const { data: ispStockData, isLoading: isLoadingIspStock } = useIspStock();
  const orderIsp = useOrderIsp();

  const liveCostInfo = useMemo(() => {
    if (!selectedProductData) return null;
    let unitPrice = Number(selectedProductData.price);
    const discounts = selectedProductData.volume_discounts || [];

    const sorted = [...discounts].sort((a: any, b: any) => b.min_qty - a.min_qty);
    for (const d of sorted) {
      if (quantity >= d.min_qty) {
        unitPrice = Number(d.price);
        break;
      }
    }

    const basePrice = Number(selectedProductData.price);
    const multiplier = isIspProduct ? quantity * ispMonths : quantity;
    const total = unitPrice * multiplier;
    const isDiscounted = unitPrice < basePrice;

    return { unitPrice, total, isDiscounted, basePrice };
  }, [selectedProductData, quantity, ispMonths, isIspProduct]);

  // Derived ISP options from stock
  const filteredStock = useMemo(() => {
    if (!ispStockData?.data || !selectedProductData) return null;

    // Check both residential and datacenter as some versions/plans move ISP between them
    const resi = ispStockData.data.residential || {};
    const dc = ispStockData.data.datacenter || {};

    let base: any = {};
    if (productType === "isp_virgin") {
      base = resi.virgin?.dedicated || dc.virgin?.dedicated || {};
    } else {
      const sharedType = productType === "isp_shared" ? "shared" : "dedicated";
      base = resi.nonvirgin?.[sharedType] || dc.nonvirgin?.[sharedType] || {};
    }

    return base;
  }, [ispStockData, selectedProductData, productType]);

  const ispCountries = useMemo(() => Object.keys(filteredStock || {}), [filteredStock]);
  const ispNames = useMemo(() => Object.keys(filteredStock?.[ispCountry] || {}), [filteredStock, ispCountry]);
  const ispCities = useMemo(() => Object.keys(filteredStock?.[ispCountry]?.[ispName] || {}), [filteredStock, ispCountry, ispName]);
  const currentStockInfo = filteredStock?.[ispCountry]?.[ispName]?.[ispCity];

  const handleOrder = useCallback(async () => {
    setError(null);
    if (!product) {
      setError("Please select a product.");
      return;
    }

    setLoading(true);
    try {
      if (isIspProduct) {
        if (!ispCountry || !ispName || !ispCity) {
          throw new Error("Please complete the location settings for static IPs.");
        }
        const result = await orderIsp.mutateAsync({
          product_id: Number(product),
          quantity,
          country_code: ispCountry,
          city: ispCity,
          isp: ispName,
          months: ispMonths,
        });
        toast({ title: "Order Successful", description: `ISP Package created. Your IPs will appear in your Active Proxies list.` });
        // Optionally redirect or show success state
      } else {
        const result = await generateProxy.mutateAsync({
          product_id: Number(product),
          quantity,
          country: country || undefined,
          session_type: sessionType as any,
        });
        setProxies(result.proxies || []);
        toast({ title: "Proxies Generated", description: `${result.proxies?.length || 0} proxies ready.` });
      }
    } catch (err: any) {
      if (err.status === 402) {
        setError("Insufficient balance. Please top up your wallet to continue.");
      } else {
        setError(err.message || "Failed to process request.");
      }
    } finally {
      setLoading(false);
    }
  }, [product, quantity, country, sessionType, generateProxy, isIspProduct, ispCountry, ispName, ispCity, ispMonths, orderIsp]);



  const copyAll = useCallback(() => {
    const text = proxies.map(formatProxyLine).join("\n");
    navigator.clipboard.writeText(text);
    toast({ title: "Copied", description: "All proxies copied to clipboard." });
  }, [proxies]);

  const exportAs = useCallback(
    (format: "txt" | "csv" | "json") => {
      let content: string;
      let mime: string;
      if (format === "json") {
        content = JSON.stringify(proxies, null, 2);
        mime = "application/json";
      } else if (format === "csv") {
        content = "host,port,username,password\n" + proxies.map((p) => `${p.host},${p.port},${p.username},${p.password}`).join("\n");
        mime = "text/csv";
      } else {
        content = proxies.map(formatProxyLine).join("\n");
        mime = "text/plain";
      }
      const blob = new Blob([content], { type: mime });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `proxies.${format}`;
      a.click();
      URL.revokeObjectURL(url);
    },
    [proxies]
  );

  const sampleProxy = proxies[0];

  return (
    <>
      <SEOHead title="Generate Proxies" noindex />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">Generate Proxies</h1>
          <Button variant="outline" asChild>
            <Link to="/app/proxies/core-residential">View Active Proxies</Link>
          </Button>
        </div>

        {/* Generator Form */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Generate Proxies</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {error && <ErrorBanner message={error} onDismiss={() => setError(null)} />}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
              <div className="space-y-2">
                <Label>Product</Label>
                <Select
                  value={product}
                  onValueChange={(val) => {
                    setProduct(val);
                    const sel = (products ?? []).find((p: any) => p.id.toString() === val);
                    if (sel) {
                      setProductType(sel.type);
                    }
                  }}
                >
                  <SelectTrigger><SelectValue placeholder="Select Product..." /></SelectTrigger>
                  <SelectContent>
                    {(products ?? []).map((p: any) => (
                      <SelectItem key={p.id} value={p.id.toString()}>{p.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {!isIspProduct ? (
                <>
                  <div className="space-y-2">
                    <Label>Country</Label>
                    <Select value={country} onValueChange={setCountry}>
                      <SelectTrigger><SelectValue placeholder="Select..." /></SelectTrigger>
                      <SelectContent>
                        {(() => {
                          const typeToUse = productType === "residential" ? "rp" : productType === "datacenter" ? "dc" : productType === "mobile" ? "mp" : productType;
                          const countriesObj = (evomiCountries as Record<string, Record<string, string>>)[typeToUse] || {};
                          return Object.entries(countriesObj).map(([code, name]) => (
                            <SelectItem key={code} value={code}>{name as string}</SelectItem>
                          ));
                        })()}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label>Session Type</Label>
                    <Select value={sessionType} onValueChange={setSessionType}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="rotating">Rotating</SelectItem>
                        <SelectItem value="sticky">Sticky</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </>
              ) : (
                <>
                  <div className="space-y-2">
                    <Label>Country</Label>
                    <Select value={ispCountry} onValueChange={(v) => { setIspCountry(v); setIspName(""); setIspCity(""); }} disabled={ispCountries.length === 0}>
                      <SelectTrigger>
                        <SelectValue placeholder={isLoadingIspStock ? "Loading..." : (ispCountries.length === 0 ? "Out of Stock" : "Select...")} />
                      </SelectTrigger>
                      <SelectContent>
                        {ispCountries.length === 0 ? (
                          <SelectItem value="out_of_stock" disabled>Out of Stock</SelectItem>
                        ) : (
                          ispCountries.map(c => <SelectItem key={c} value={c}>{c}</SelectItem>)
                        )}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label>ISP</Label>
                    <Select value={ispName} onValueChange={(v) => { setIspName(v); setIspCity(""); }} disabled={!ispCountry || ispNames.length === 0}>
                      <SelectTrigger>
                        <SelectValue placeholder={(!ispCountry) ? "Select Country First" : (ispNames.length === 0 ? "Out of Stock" : "Select...")} />
                      </SelectTrigger>
                      <SelectContent>
                        {ispNames.length === 0 ? (
                          <SelectItem value="out_of_stock" disabled>Out of Stock</SelectItem>
                        ) : (
                          ispNames.map(i => <SelectItem key={i} value={i}>{i.charAt(0).toUpperCase() + i.slice(1)}</SelectItem>)
                        )}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label>City</Label>
                    <Select value={ispCity} onValueChange={setIspCity} disabled={!ispName || ispCities.length === 0}>
                      <SelectTrigger>
                        <SelectValue placeholder={(!ispName) ? "Select ISP First" : (ispCities.length === 0 ? "Out of Stock" : "Select...")} />
                      </SelectTrigger>
                      <SelectContent>
                        {ispCities.length === 0 ? (
                          <SelectItem value="out_of_stock" disabled>Out of Stock</SelectItem>
                        ) : (
                          ispCities.map(c => <SelectItem key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</SelectItem>)
                        )}
                      </SelectContent>
                    </Select>
                  </div>
                </>
              )}

              <div className="space-y-2">
                <Label>Quantity</Label>
                <Input
                  type="number"
                  min={1}
                  max={1000}
                  value={quantity}
                  onChange={(e) => setQuantity(Number(e.target.value))}
                />
              </div>

              {isIspProduct && (
                <div className="space-y-2">
                  <Label>Duration (Months)</Label>
                  <Select value={ispMonths.toString()} onValueChange={(v) => setIspMonths(Number(v))}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="1">1 Month</SelectItem>
                      <SelectItem value="3">3 Months</SelectItem>
                      <SelectItem value="6">6 Months</SelectItem>
                      <SelectItem value="12">12 Months</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              )}
            </div>

            {liveCostInfo && (
              <div className="flex items-center justify-between bg-muted/50 p-4 rounded-md border border-border/50">
                <div>
                  <p className="text-sm font-medium">{isIspProduct ? "Total Package Cost" : "Estimated Cost"}</p>
                  <p className="text-xs text-muted-foreground">
                    {quantity} {isIspProduct ? "IPs" : productType === "dc_unmetered" ? "Months" : "GB"}
                    {isIspProduct && ` x ${ispMonths} mo`} @ {format(liveCostInfo.unitPrice)}/ea
                    {liveCostInfo.isDiscounted && (
                      <span className="line-through text-muted-foreground/50 ml-2">{format(liveCostInfo.basePrice)}</span>
                    )}
                  </p>
                  {isIspProduct && currentStockInfo && (
                    <p className="text-[10px] text-green-600 font-medium mt-1">
                      In Stock: {currentStockInfo.stock} IPs
                    </p>
                  )}
                </div>
                <div className="text-right flex flex-col items-end">
                  <p className="text-lg font-bold text-primary">{format(liveCostInfo.total)}</p>
                  {liveCostInfo.isDiscounted && (
                    <Badge variant="secondary" className="bg-green-500/10 text-green-600 border-green-200 mt-1 text-[10px] px-1.5 py-0 h-4">
                      Volume Discount
                    </Badge>
                  )}
                </div>
              </div>
            )}

            <div className="flex flex-wrap gap-2 pt-2">
              <Button onClick={handleOrder} disabled={loading || (isIspProduct && !currentStockInfo)}>
                {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                {loading ? t("common.loading") : isIspProduct ? "Order Static IPs" : "Generate"}
              </Button>

              {error && (error.toLowerCase().includes("insufficient balance") || error.toLowerCase().includes("low balance")) && (
                <Button variant="destructive" asChild className="gap-2">
                  <Link to="/app/billing">
                    <CreditCard className="h-4 w-4" />
                    Add Credit
                  </Link>
                </Button>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Results */}
        {proxies.length > 0 && (
          <Tabs defaultValue="output">
            <div className="flex items-center justify-between flex-wrap gap-2">
              <TabsList>
                <TabsTrigger value="output">Output</TabsTrigger>
                <TabsTrigger value="table">Table View</TabsTrigger>
                <TabsTrigger value="snippets">Config Snippets</TabsTrigger>
              </TabsList>
            </div>

            {/* Plain-text output window */}
            <TabsContent value="output" className="mt-4">
              <Card>
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-sm">
                    <Badge variant="secondary" className="mr-2">{proxies.length}</Badge>
                    proxies generated • {product} • {country} • {sessionType}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <Textarea
                    readOnly
                    className="font-mono text-xs leading-relaxed min-h-[200px] max-h-[400px] resize-y bg-muted"
                    value={proxies.map(formatProxyLine).join("\n")}
                    onFocus={(e) => e.target.select()}
                  />
                  <div className="flex flex-wrap gap-2">
                    <Button variant="default" size="sm" onClick={copyAll}>
                      <Copy className="mr-1.5 h-3.5 w-3.5" /> Copy All
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => exportAs("txt")}>
                      <Download className="mr-1.5 h-3.5 w-3.5" /> Download .txt
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => exportAs("csv")}>
                      <Download className="mr-1.5 h-3.5 w-3.5" /> Download .csv
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => exportAs("json")}>
                      <Download className="mr-1.5 h-3.5 w-3.5" /> Download .json
                    </Button>
                    <Button variant="secondary" size="sm" asChild className="ml-auto">
                      <Link to={`/app/proxies/${SLUG_MAP[productType] || "residential"}`}>
                        View in {SLUG_MAP[productType]?.split("-").map(s => s.charAt(0).toUpperCase() + s.slice(1)).join(" ") || "Residential"} Tab
                      </Link>
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            {/* Table view */}
            <TabsContent value="table" className="mt-4">
              <Card>
                <CardContent className="p-0">
                  <div className="max-h-96 overflow-auto">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="w-12">#</TableHead>
                          <TableHead>Host</TableHead>
                          <TableHead>Port</TableHead>
                          <TableHead>Username</TableHead>
                          <TableHead>Password</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {proxies.map((p, i) => (
                          <TableRow key={i}>
                            <TableCell className="text-muted-foreground">{i + 1}</TableCell>
                            <TableCell className="font-mono text-xs">{p.host}</TableCell>
                            <TableCell className="font-mono text-xs">{p.port}</TableCell>
                            <TableCell className="font-mono text-xs">{p.username}</TableCell>
                            <TableCell className="font-mono text-xs">{p.password}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="snippets" className="mt-4 space-y-4">
              {sampleProxy && (
                <>
                  <SnippetBlock title="cURL" code={generateCurl(sampleProxy)} />
                  <SnippetBlock title="Python (requests)" code={generatePython(sampleProxy)} />
                  <SnippetBlock title="Node.js (fetch)" code={generateNode(sampleProxy)} />
                </>
              )}
            </TabsContent>
          </Tabs>
        )}
      </div>

    </>
  );
}

function SnippetBlock({ title, code }: { title: string; code: string }) {
  const copy = () => {
    navigator.clipboard.writeText(code);
    toast({ title: "Copied", description: `${title} snippet copied.` });
  };
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <CardTitle className="text-sm">{title}</CardTitle>
        <Button variant="ghost" size="sm" onClick={copy}>
          <Copy className="mr-1 h-3.5 w-3.5" /> Copy
        </Button>
      </CardHeader>
      <CardContent>
        <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs leading-relaxed">{code}</pre>
      </CardContent>
    </Card>
  );
}
